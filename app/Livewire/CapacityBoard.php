<?php

namespace App\Livewire;

use App\Events\BoardUpdated;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BountyCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class CapacityBoard extends Component
{
    public int $tenantId = 0;

    public string $tenantName = '';

    // Department Radar filter (null = All Departments)
    public ?int $selectedDepartmentId = null;

    // View Density Toggle (compact by default)
    public bool $isCompact = true;

    // Create Post-It Modal form properties
    #[Validate('required|string|min:3|max:255')]
    public string $title = '';

    #[Validate('nullable|string|max:1000')]
    public string $description = '';

    #[Validate('nullable|integer|exists:departments,id')]
    public ?int $departmentId = null;

    #[Validate('required|in:low,normal,high,urgent')]
    public string $priority = 'normal';

    #[Validate('required|integer|min:1|max:5')]
    public int $weight = 1;

    #[Validate('nullable|integer|exists:users,id')]
    public ?int $assigneeId = null;

    public bool $showCreateModal = false;

    // Co-op Assist Modal properties
    public bool $showCoOpModal = false;

    public ?int $coOpPostItId = null;

    public ?int $coOpHelperId = null;

    #[Validate('required|integer|min:0|max:50')]
    public int $coOpHelperSplit = 20;

    /**
     * Mount the component and initialize the tenant.
     */
    public function mount(): void
    {
        $tenant = Auth::user()?->tenant
            ?? Tenant::where('slug', 'hellenic-demo-ops')->first()
            ?? Tenant::firstOrFail();

        $this->tenantId = $tenant->id;
        $this->tenantName = $tenant->name;
    }

    /**
     * Log out the current user and redirect to landing page.
     */
    public function logout(): mixed
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Select a department tab on the Radar Bar.
     */
    public function selectDepartment(?int $departmentId = null): void
    {
        $this->selectedDepartmentId = $departmentId;
    }

    /**
     * Toggle high-density compact vs detailed card view.
     */
    public function toggleCompactView(): void
    {
        $this->isCompact = ! $this->isCompact;
    }

    /**
     * Open the Co-op / Assist assignment modal for an active task.
     */
    public function openCoOpModal(int $postItId): void
    {
        $postIt = PostIt::where('tenant_id', $this->tenantId)->findOrFail($postItId);

        $currentUser = Auth::user();
        $canManage = $currentUser && ($currentUser->role === 'admin' || (int) $currentUser->id === (int) $postIt->assignee_id);

        if (! $canManage) {
            return;
        }

        $this->coOpPostItId = $postIt->id;
        $this->coOpHelperId = $postIt->helper_id;
        $this->coOpHelperSplit = $postIt->helper_split > 0 ? $postIt->helper_split : 20;
        $this->showCoOpModal = true;
    }

    /**
     * Save the Co-op helper and bounty split percentage.
     */
    public function saveCoOpSplit(): void
    {
        $this->validate([
            'coOpHelperSplit' => 'required|integer|min:0|max:50',
            'coOpHelperId' => 'nullable|integer|exists:users,id',
        ]);

        if ($this->coOpPostItId === null) {
            return;
        }

        $postIt = PostIt::where('tenant_id', $this->tenantId)->findOrFail($this->coOpPostItId);

        $currentUser = Auth::user();
        $canManage = $currentUser && ($currentUser->role === 'admin' || (int) $currentUser->id === (int) $postIt->assignee_id);

        if (! $canManage) {
            return;
        }

        $postIt->update([
            'helper_id' => $this->coOpHelperId,
            'helper_split' => $this->coOpHelperId !== null ? min(50, max(0, $this->coOpHelperSplit)) : 0,
        ]);

        $this->showCoOpModal = false;
        $this->coOpPostItId = null;

        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Move a post-it to a target column/worker and update capacity & bounty splits.
     */
    public function movePostIt(int $postItId, ?int $assigneeId, string $targetStatus): void
    {
        $postIt = PostIt::where('tenant_id', $this->tenantId)->findOrFail($postItId);

        $previousAssigneeId = $postIt->assignee_id;
        $previousStatus = $postIt->status;

        $targetStatus = match ($targetStatus) {
            'doing' => 'doing',
            'done' => 'done',
            default => 'backlog',
        };

        if ($targetStatus === 'backlog') {
            $assigneeId = null;
        }

        $updates = [
            'status' => $targetStatus,
            'assignee_id' => $assigneeId,
        ];

        if ($targetStatus === 'doing' && $previousStatus !== 'doing') {
            $updates['started_at'] = now();
        }

        if ($targetStatus === 'done') {
            $updates['completed_at'] = now();
        } elseif ($previousStatus === 'done' && $targetStatus !== 'done') {
            $updates['completed_at'] = null;
        }

        $postIt->update($updates);

        // Recalculate current_capacity for affected workers
        $affectedUserIds = array_unique(array_filter([$previousAssigneeId, $assigneeId]));

        foreach ($affectedUserIds as $userId) {
            $user = User::find($userId);
            if ($user !== null) {
                $user->update([
                    'current_capacity' => (int) PostIt::where('assignee_id', $userId)
                        ->where('status', 'doing')
                        ->sum('weight'),
                ]);
            }
        }

        // Award automated bounty points & co-op split upon completion
        if ($targetStatus === 'done' && $previousStatus !== 'done') {
            $calculator = app(BountyCalculator::class);
            $calculator->awardPayout($postIt->fresh());
        }

        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Create a new post-it card with optional direct collaborator assignment.
     */
    public function createPostIt(): void
    {
        $this->validate();

        $requester = Auth::user()
            ?? User::where('tenant_id', $this->tenantId)->where('role', 'client')->first()
            ?? User::where('tenant_id', $this->tenantId)->first();

        $calculator = app(BountyCalculator::class);
        $bountyPoints = $calculator->calculate(
            weight: $this->weight,
            priority: $this->priority,
            tenantId: $this->tenantId,
        );

        $isAssigned = $this->assigneeId !== null;
        $status = $isAssigned ? 'doing' : 'backlog';

        $postIt = PostIt::create([
            'tenant_id' => $this->tenantId,
            'department_id' => $this->departmentId,
            'requester_id' => $requester->id,
            'assignee_id' => $this->assigneeId,
            'title' => $this->title,
            'description' => $this->description !== '' ? $this->description : null,
            'status' => $status,
            'priority' => $this->priority,
            'weight' => $this->weight,
            'bounty_points' => $bountyPoints,
            'started_at' => $isAssigned ? now() : null,
        ]);

        if ($isAssigned) {
            $assignedWorker = User::find($this->assigneeId);
            if ($assignedWorker !== null) {
                $assignedWorker->update([
                    'current_capacity' => (int) PostIt::where('assignee_id', $assignedWorker->id)
                        ->where('status', 'doing')
                        ->sum('weight'),
                ]);
            }
        }

        $this->reset(['title', 'description', 'weight', 'priority', 'departmentId', 'assigneeId', 'showCreateModal']);

        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Sanitize a PostIt record based on the privacy & visibility matrix.
     */
    protected function sanitizePostIt(PostIt $postIt, ?User $currentUser): void
    {
        $isOwn = $currentUser && (int) $postIt->requester_id === (int) $currentUser->id;
        $postIt->is_own = $isOwn;

        if (! $currentUser || $currentUser->role === 'admin') {
            $postIt->display_requester_name = $postIt->requester?->name ?? 'User #'.$postIt->requester_id;
            $postIt->is_masked = false;

            return;
        }

        if ($isOwn) {
            $postIt->display_requester_name = $postIt->requester?->name ?? 'Εγώ';
            $postIt->is_masked = false;
        } else {
            $postIt->display_requester_name = 'Ανώνυμο Αίτημα';
            if ($currentUser->role === 'client') {
                $postIt->is_masked = true;
                $postIt->description = null;
            } else {
                $postIt->is_masked = false;
            }
        }
    }

    /**
     * Render the Livewire component.
     */
    public function render(): View
    {
        $currentUser = Auth::user();

        // 1. Department Radar Calculation
        $departments = Department::where('tenant_id', $this->tenantId)->get();

        $allTenantWorkers = User::where('tenant_id', $this->tenantId)
            ->where('role', 'worker')
            ->get();

        $allCurrentCap = (int) $allTenantWorkers->sum('current_capacity');
        $allMaxCap = (int) $allTenantWorkers->sum('max_capacity');
        $allRatio = $allMaxCap > 0 ? (int) round(($allCurrentCap / $allMaxCap) * 100) : 0;

        $radarTabs = [
            [
                'id' => null,
                'name' => 'All Departments',
                'color' => '#6366f1',
                'current_capacity' => $allCurrentCap,
                'max_capacity' => $allMaxCap,
                'ratio' => $allRatio,
                'status' => $allRatio >= 100 ? 'danger' : ($allRatio >= 70 ? 'warning' : 'healthy'),
            ],
        ];

        foreach ($departments as $dept) {
            $deptWorkers = $allTenantWorkers->where('department_id', $dept->id);
            $cur = (int) $deptWorkers->sum('current_capacity');
            $max = (int) $deptWorkers->sum('max_capacity');
            $rat = $max > 0 ? (int) round(($cur / $max) * 100) : 0;

            $radarTabs[] = [
                'id' => $dept->id,
                'name' => $dept->name,
                'color' => $dept->color,
                'current_capacity' => $cur,
                'max_capacity' => $max,
                'ratio' => $rat,
                'status' => $rat >= 100 ? 'danger' : ($rat >= 70 ? 'warning' : 'healthy'),
            ];
        }

        // 2. Query Workers for Columns (filtered by selected department)
        $workersQuery = User::where('tenant_id', $this->tenantId)
            ->where('role', 'worker')
            ->with(['department', 'assignedPostIts' => function ($query): void {
                $query->where('status', 'doing')->with(['requester', 'helper', 'department']);
            }])
            ->orderBy('name');

        if ($this->selectedDepartmentId !== null) {
            $workersQuery->where('department_id', $this->selectedDepartmentId);
        }

        $workers = $workersQuery->get();

        // 3. Query Backlog Post-Its (filtered by selected department)
        $backlogQuery = PostIt::where('tenant_id', $this->tenantId)
            ->where(function ($query): void {
                $query->where('status', 'backlog')
                    ->orWhereNull('assignee_id');
            })
            ->with(['requester', 'department'])
            ->latest('id');

        if ($this->selectedDepartmentId !== null) {
            $backlogQuery->where('department_id', $this->selectedDepartmentId);
        }

        $backlogPostIts = $backlogQuery->get();

        // 4. Query Done Post-Its (with strict privacy visibility scoping)
        $doneQuery = PostIt::where('tenant_id', $this->tenantId)
            ->where('status', 'done');

        if ($this->selectedDepartmentId !== null) {
            $doneQuery->where('department_id', $this->selectedDepartmentId);
        }

        // Non-admin users ONLY query done post-its where they are requester, assignee, or helper
        if ($currentUser && $currentUser->role !== 'admin') {
            $doneQuery->where(function ($query) use ($currentUser): void {
                $query->where('requester_id', $currentUser->id)
                    ->orWhere('assignee_id', $currentUser->id)
                    ->orWhere('helper_id', $currentUser->id);
            });
        }

        $donePostIts = $doneQuery->with(['requester', 'assignee', 'helper', 'department'])
            ->latest('completed_at')
            ->get();

        // 5. Sanitize cards for privacy & visibility rules
        foreach ($backlogPostIts as $postIt) {
            $this->sanitizePostIt($postIt, $currentUser);
        }

        foreach ($workers as $worker) {
            foreach ($worker->assignedPostIts as $postIt) {
                $this->sanitizePostIt($postIt, $currentUser);
            }
        }

        foreach ($donePostIts as $postIt) {
            $this->sanitizePostIt($postIt, $currentUser);
        }

        // Available workers for direct collaborator selection in the create modal
        $availableCollaborators = User::where('tenant_id', $this->tenantId)
            ->where('role', 'worker')
            ->with('department')
            ->orderBy('name')
            ->get();

        return view('livewire.capacity-board', [
            'radarTabs' => $radarTabs,
            'departments' => $departments,
            'workers' => $workers,
            'backlogPostIts' => $backlogPostIts,
            'donePostIts' => $donePostIts,
            'availableCollaborators' => $availableCollaborators,
        ])->layout('layouts.app');
    }
}
