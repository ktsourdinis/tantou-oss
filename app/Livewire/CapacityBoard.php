<?php

namespace App\Livewire;

use App\Events\BoardUpdated;
use App\Livewire\Concerns\ManagesWorkspaceSettings;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\PostItAttachment;
use App\Models\PostItTransfer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BountyCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class CapacityBoard extends Component
{
    use ManagesWorkspaceSettings;
    use WithFileUploads;

    public int $tenantId = 0;

    public string $tenantName = '';

    // Department Radar filter (null = All Departments)
    public ?int $selectedDepartmentId = null;

    // View Density Toggle (compact by default)
    public bool $isCompact = true;

    // Completed tasks filter (quick toggle for staff to view Mine / My Facility)
    public bool $filterCompletedMineOnly = false;

    // Requests History Modal properties
    public bool $showRequestsHistoryModal = false;

    public string $historySearch = '';

    public string $historyStatusFilter = 'all'; // 'all', 'active', 'completed'

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

    /** @var array<int, TemporaryUploadedFile> */
    public array $newAttachments = [];

    public bool $showCreateModal = false;

    // Co-op Assist Modal properties
    public bool $showCoOpModal = false;

    public ?int $coOpPostItId = null;

    public ?int $coOpHelperId = null;

    public int $coOpHelperSplit = 20;

    // Slide-Over Detail, Scope Calibration & Micro-Comments
    public bool $showSlideOver = false;

    public ?int $selectedPostItId = null;

    public int $slideOverWeight = 1;

    public string $slideOverPriority = 'normal';

    public ?int $slideOverHelperId = null;

    public int $slideOverHelperSplit = 20;

    public string $newCommentBody = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $slideOverNewAttachments = [];

    // Post-It Detail/Edit Modal inline editing properties
    public bool $isEditingDetail = false;

    #[Validate('required|string|min:3|max:255')]
    public string $editTitle = '';

    #[Validate('nullable|string|max:1000')]
    public string $editDescription = '';

    #[Validate('required|in:low,normal,high,urgent')]
    public string $editPriority = 'normal';

    #[Validate('required|integer|min:1|max:5')]
    public int $editWeight = 1;

    #[Validate('nullable|integer|exists:departments,id')]
    public ?int $editDepartmentId = null;

    #[Validate('nullable|integer|exists:users,id')]
    public ?int $editAssigneeId = null;

    public string $requesterResolutionReason = '';

    /**
     * Mount the component and initialize the tenant.
     */
    public function mount(): void
    {
        $tenant = app()->bound('currentTenant')
            ? app('currentTenant')
            : (Auth::user()?->tenant
                ?? Tenant::where('slug', 'hellenic-demo-ops')->first()
                ?? Tenant::firstOrFail());

        $this->tenantId = $tenant->id;
        $this->tenantName = $tenant->name;
        $this->tenantSlug = $tenant->slug;

        $this->resetCreateForm();
    }

    /**
     * Check if a user is permitted to assign tasks to a target collaborator based on firewall & column visibility rules.
     */
    public function canAssignTo(?User $authUser, User $targetCollaborator): bool
    {
        return $authUser?->canAssignTo($targetCollaborator) ?? false;
    }

    /**
     * Open the Create Post-it modal and initialize smart defaults or contextual target.
     */
    public function openCreateModal(?int $targetUserId = null, bool $forceBacklog = false): void
    {
        $this->resetCreateForm();
        $currentUser = Auth::user();

        if ($forceBacklog) {
            $this->assigneeId = null;
            $this->departmentId = $this->selectedDepartmentId ?? $currentUser?->department_id;
        } elseif ($targetUserId !== null) {
            $targetCollaborator = User::where('tenant_id', $this->tenantId)->find($targetUserId);

            if ($targetCollaborator && $targetCollaborator->is_available && $this->canAssignTo($currentUser, $targetCollaborator)) {
                $this->departmentId = $targetCollaborator->department_id;
                $this->assigneeId = $targetCollaborator->id;
            } else {
                $this->assigneeId = null;
                $this->departmentId = $targetCollaborator?->department_id ?? $this->selectedDepartmentId ?? $currentUser?->department_id;
            }
        }

        $this->showCreateModal = true;
    }

    /**
     * Contextual quick-create helper for a specific collaborator.
     */
    public function openCreateForCollaborator(int $userId): void
    {
        $this->openCreateModal(targetUserId: $userId);
    }

    /**
     * Contextual quick-create helper for unassigned backlog.
     */
    public function openCreateForBacklog(): void
    {
        $this->openCreateModal(forceBacklog: true);
    }

    /**
     * Reset the Create Post-it modal form and apply smart defaults based on user role.
     */
    public function resetCreateForm(): void
    {
        $this->reset(['title', 'description', 'newAttachments']);
        $this->priority = 'normal';
        $this->weight = 1;
        $this->departmentId = null;
        $this->assigneeId = null;

        $currentUser = Auth::user();
        if ($currentUser) {
            $isCollaborator = $currentUser->isWorker() || in_array($currentUser->role, ['worker', 'collaborator'], true);
            $isCoordinator = $currentUser->isCoordinator() || $currentUser->role === 'coordinator';

            if ($isCollaborator) {
                $this->departmentId = $currentUser->department_id;
                $this->assigneeId = $currentUser->id;
            } elseif ($isCoordinator) {
                $this->departmentId = $currentUser->department_id ?? $this->selectedDepartmentId;
                $this->assigneeId = null;
            } elseif ($this->selectedDepartmentId) {
                $this->departmentId = $this->selectedDepartmentId;
            }
        }
    }

    /**
     * Triggered when showCreateModal changes state.
     */
    public function updatedShowCreateModal(bool $value): void
    {
        if ($value) {
            $this->resetCreateForm();
        }
    }

    /**
     * Triggered when departmentId is changed in the Create Post-it form.
     */
    public function updatedDepartmentId(?int $value): void
    {
        if ($value && $this->assigneeId) {
            $assignee = User::where('tenant_id', $this->tenantId)->find($this->assigneeId);
            if ($assignee && $assignee->department_id && (int) $assignee->department_id !== (int) $value) {
                $this->assigneeId = null;
            }
        }
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
     * Switch application locale.
     */
    public function switchLocale(string $locale): void
    {
        if (! in_array($locale, ['en', 'el'], true)) {
            return;
        }

        session()->put('locale', $locale);

        if (Auth::check()) {
            Auth::user()->update(['locale' => $locale]);
        }

        app()->setLocale($locale);

        $this->redirect(request()->header('Referer', route('board')));
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

        $postIt = PostIt::where('tenant_id', $this->tenantId)->with('assignee')->findOrFail($this->coOpPostItId);

        $currentUser = Auth::user();
        $isCoordinator = $currentUser && $currentUser->role === 'coordinator' && (
            (int) $currentUser->department_id === (int) $postIt->department_id ||
            (int) $currentUser->department_id === (int) $postIt->assignee?->department_id
        );
        $canManage = $currentUser && ($currentUser->role === 'admin' || $isCoordinator || (int) $currentUser->id === (int) $postIt->assignee_id);

        if (! $canManage) {
            return;
        }

        // Enforce Co-op matching & self-assignment prevention
        if ($this->coOpHelperId !== null) {
            if ($this->coOpHelperId === (int) $postIt->assignee_id) {
                return; // User cannot be assigned as their own helper
            }

            $helper = User::where('tenant_id', $this->tenantId)->find($this->coOpHelperId);
            $assigneeVisibility = $postIt->assignee?->board_visibility;

            if (! $helper || $assigneeVisibility === 'private' || $helper->board_visibility !== $assigneeVisibility) {
                return; // Mode mismatch or private column
            }
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
     * Start working on an assigned post-it card (transitions 'assigned' -> 'doing').
     */
    public function startPostIt(int $postItId): void
    {
        $postIt = PostIt::where('tenant_id', $this->tenantId)->with('assignee')->findOrFail($postItId);

        $currentUser = Auth::user();
        if (! $currentUser) {
            return;
        }

        $canStart = $currentUser->role === 'admin'
            || (int) $postIt->assignee_id === (int) $currentUser->id
            || (
                $currentUser->role === 'coordinator' &&
                (
                    (int) $postIt->department_id === (int) $currentUser->department_id ||
                    (int) $postIt->assignee?->department_id === (int) $currentUser->department_id
                )
            );

        if (! $canStart) {
            return;
        }

        $postIt->update([
            'status' => 'doing',
            'started_at' => now(),
        ]);

        PostItTransfer::create([
            'post_it_id' => $postIt->id,
            'actor_id' => $currentUser->id,
            'from_user_id' => $postIt->assignee_id,
            'to_user_id' => $postIt->assignee_id,
            'action' => 'started',
            'created_at' => now(),
        ]);

        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Toggle collaborator working / away availability state.
     */
    public function toggleAvailability(int $userId): void
    {
        $currentUser = Auth::user();
        if (! $currentUser) {
            return;
        }

        $targetUser = User::where('tenant_id', $this->tenantId)->findOrFail($userId);

        $canToggle = $currentUser->role === 'admin'
            || (int) $currentUser->id === (int) $targetUser->id
            || (
                $currentUser->role === 'coordinator' &&
                $targetUser->department_id !== null &&
                (int) $targetUser->department_id === (int) $currentUser->department_id
            );

        if (! $canToggle) {
            return;
        }

        $targetUser->update([
            'is_available' => ! $targetUser->is_available,
        ]);

        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Update collaborator board visibility mode (public, internal, private, none).
     */
    public function updateBoardVisibility(int $userId, string $visibility): void
    {
        if (! in_array($visibility, ['public', 'internal', 'private', 'none'], true)) {
            return;
        }

        $currentUser = Auth::user();
        if (! $currentUser) {
            abort(401);
        }

        $targetUser = User::where('tenant_id', $this->tenantId)->findOrFail($userId);

        if (! $currentUser->canChangeBoardVisibilityOf($targetUser)) {
            abort(403, 'Unauthorized to change column visibility.');
        }

        $targetUser->update([
            'board_visibility' => $visibility,
        ]);

        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Move a post-it to a target column/worker and update capacity & bounty splits.
     */
    public function movePostIt(int $postItId, ?int $assigneeId, string $targetStatus): void
    {
        $postIt = PostIt::where('tenant_id', $this->tenantId)->with(['assignee', 'requester'])->findOrFail($postItId);

        $currentUser = Auth::user();
        if (! $currentUser) {
            return;
        }

        // Strict role-based guardrails:
        // - admin: Can drag/move any card across any column.
        // - coordinator: Can rebalance cards across collaborators within their department.
        // - worker: Can pull cards from backlog, move cards where they are assignee or helper, and transition to done.
        // - client: CAN drag own unstarted cards ('backlog' or 'assigned', started_at === null) between available public workers.
        if (! $this->canMovePostIt($postIt, $currentUser)) {
            return;
        }

        if ($targetStatus === 'done') {
            $this->dropIntoCompleted($postItId);

            return;
        }

        $requester = $postIt->requester ?? ($postIt->requester_id ? User::find($postIt->requester_id) : null);
        $isClientRequest = ($requester && $requester->role === 'client') || ($requester === null && $postIt->guest_name !== null);

        // Strict Firewall: Client requests CANNOT enter 'internal' or 'private' columns,
        // even if an admin, coordinator, or worker attempts the move.
        // 'internal' columns can only receive Post-its created by internal staff (worker, coordinator, admin).
        if ($isClientRequest && $assigneeId !== null) {
            $targetCollaborator = User::where('tenant_id', $this->tenantId)->find($assigneeId);
            if (! $targetCollaborator || $targetCollaborator->board_visibility !== 'public') {
                return;
            }

            if ($currentUser->role === 'client' && ! $targetCollaborator->is_available) {
                return;
            }
        }

        // Specific client restrictions on target
        if ($currentUser->role === 'client') {
            if ($targetStatus === 'done') {
                return; // Clients cannot mark tasks as done
            }
        }

        if ($currentUser->role === 'worker') {
            if ($targetStatus === 'done' && ! ((int) $postIt->assignee_id === (int) $currentUser->id || (int) $postIt->helper_id === (int) $currentUser->id)) {
                return;
            }
        }

        $previousAssigneeId = $postIt->assignee_id;
        $previousStatus = $postIt->status;

        $targetStatus = match ($targetStatus) {
            'done' => 'done',
            'doing' => 'doing',
            'assigned' => 'assigned',
            default => 'backlog',
        };

        if ($targetStatus === 'backlog' || $assigneeId === null) {
            $assigneeId = null;
            $targetStatus = 'backlog';
        } elseif ($targetStatus === 'doing') {
            $targetStatus = 'doing';
        } elseif ($targetStatus === 'done') {
            $targetStatus = 'done';
        } else {
            $targetStatus = 'assigned';
        }

        $updates = [
            'status' => $targetStatus,
            'assignee_id' => $assigneeId,
        ];

        // Status transition rules & transfer history:
        if ($targetStatus === 'backlog') {
            // Automatically reset helper_id = null, helper_split = 0, and started_at = null
            $updates['helper_id'] = null;
            $updates['helper_split'] = 0;
            $updates['started_at'] = null;
            $updates['assigned_by_id'] = null;

            if ($previousAssigneeId !== null) {
                PostItTransfer::create([
                    'post_it_id' => $postIt->id,
                    'actor_id' => $currentUser->id,
                    'from_user_id' => $previousAssigneeId,
                    'to_user_id' => null,
                    'action' => 'unassigned',
                    'created_at' => now(),
                ]);
            }
        } elseif ($targetStatus === 'assigned') {
            $updates['started_at'] = null;

            if ($previousAssigneeId === null && $assigneeId !== null) {
                $updates['assigned_by_id'] = $currentUser->id;
                PostItTransfer::create([
                    'post_it_id' => $postIt->id,
                    'actor_id' => $currentUser->id,
                    'from_user_id' => null,
                    'to_user_id' => $assigneeId,
                    'action' => 'assigned',
                    'created_at' => now(),
                ]);
            } elseif ($previousAssigneeId !== null && $assigneeId !== null && (int) $previousAssigneeId !== (int) $assigneeId) {
                $updates['assigned_by_id'] = $currentUser->id;
                PostItTransfer::create([
                    'post_it_id' => $postIt->id,
                    'actor_id' => $currentUser->id,
                    'from_user_id' => $previousAssigneeId,
                    'to_user_id' => $assigneeId,
                    'action' => 'transferred',
                    'created_at' => now(),
                ]);
            }

            // Fix self-helper if reassigning
            if ($postIt->helper_id !== null) {
                if ((int) $postIt->helper_id === (int) $assigneeId) {
                    $updates['helper_id'] = null;
                    $updates['helper_split'] = 0;
                } else {
                    $newAssignee = User::find($assigneeId);
                    $existingHelper = User::find($postIt->helper_id);
                    if ($newAssignee?->board_visibility === 'private' || $existingHelper?->board_visibility !== $newAssignee?->board_visibility) {
                        $updates['helper_id'] = null;
                        $updates['helper_split'] = 0;
                    }
                }
            }
        } elseif ($targetStatus === 'doing') {
            if ($previousStatus !== 'doing' || $postIt->started_at === null) {
                $updates['started_at'] = now();
            }

            if ($previousAssigneeId === null && $assigneeId !== null) {
                $updates['assigned_by_id'] = $currentUser->id;
                PostItTransfer::create([
                    'post_it_id' => $postIt->id,
                    'actor_id' => $currentUser->id,
                    'from_user_id' => null,
                    'to_user_id' => $assigneeId,
                    'action' => 'assigned',
                    'created_at' => now(),
                ]);
            } elseif ($previousAssigneeId !== null && $assigneeId !== null && (int) $previousAssigneeId !== (int) $assigneeId) {
                $updates['assigned_by_id'] = $currentUser->id;
                PostItTransfer::create([
                    'post_it_id' => $postIt->id,
                    'actor_id' => $currentUser->id,
                    'from_user_id' => $previousAssigneeId,
                    'to_user_id' => $assigneeId,
                    'action' => 'transferred',
                    'created_at' => now(),
                ]);
            }

            if ($previousStatus !== 'doing') {
                PostItTransfer::create([
                    'post_it_id' => $postIt->id,
                    'actor_id' => $currentUser->id,
                    'from_user_id' => $assigneeId,
                    'to_user_id' => $assigneeId,
                    'action' => 'started',
                    'created_at' => now(),
                ]);
            }
        }

        if ($targetStatus === 'done') {
            $updates['completed_at'] = now();
            if ($previousStatus !== 'done') {
                PostItTransfer::create([
                    'post_it_id' => $postIt->id,
                    'actor_id' => $currentUser->id,
                    'from_user_id' => $postIt->assignee_id,
                    'to_user_id' => $postIt->assignee_id,
                    'action' => 'completed',
                    'created_at' => now(),
                ]);
            }
        } elseif ($previousStatus === 'done' && $targetStatus !== 'done') {
            $updates['completed_at'] = null;
        }

        $postIt->update($updates);

        // Recalculate current_capacity for affected workers (both 'assigned' and 'doing' count)
        $affectedUserIds = array_unique(array_filter([$previousAssigneeId, $assigneeId]));

        foreach ($affectedUserIds as $userId) {
            $user = User::find($userId);
            if ($user !== null) {
                $user->update([
                    'current_capacity' => (int) PostIt::where('assignee_id', $userId)
                        ->whereIn('status', ['assigned', 'doing'])
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
        $this->validate([
            'title' => 'required|string|min:3|max:255',
            'description' => 'nullable|string|max:1000',
            'departmentId' => 'nullable|integer|exists:departments,id',
            'priority' => 'required|in:low,normal,high,urgent',
            'weight' => 'required|integer|min:1|max:5',
            'assigneeId' => 'nullable|integer|exists:users,id',
            'newAttachments' => 'nullable|array|max:5',
            'newAttachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,csv,txt,zip',
        ]);

        $requester = Auth::user()
            ?? User::where('tenant_id', $this->tenantId)->where('role', 'client')->first()
            ?? User::where('tenant_id', $this->tenantId)->first();

        // If client assigning, verify collaborator is available and public
        if ($requester->role === 'client' && $this->assigneeId !== null) {
            $collaborator = User::where('tenant_id', $this->tenantId)->find($this->assigneeId);
            if (! $collaborator || ! $collaborator->is_available || $collaborator->board_visibility !== 'public') {
                $this->addError('assigneeId', __('This collaborator is currently away or not available for external requests.'));

                return;
            }
        }

        $calculator = app(BountyCalculator::class);
        $bountyPoints = $calculator->calculate(
            weight: $this->weight,
            priority: $this->priority,
            tenantId: $this->tenantId,
        );

        $isAssigned = $this->assigneeId !== null;
        $status = $isAssigned ? 'assigned' : 'backlog';

        $postIt = PostIt::create([
            'tenant_id' => $this->tenantId,
            'department_id' => $this->departmentId,
            'requester_id' => $requester->id,
            'facility_name' => Auth::user()?->facility_name ?? $requester->facility_name ?? null,
            'assignee_id' => $this->assigneeId,
            'assigned_by_id' => $isAssigned ? $requester->id : null,
            'title' => $this->title,
            'description' => $this->description !== '' ? $this->description : null,
            'status' => $status,
            'priority' => $this->priority,
            'weight' => $this->weight,
            'bounty_points' => $bountyPoints,
            'started_at' => null,
        ]);

        if (! empty($this->newAttachments)) {
            foreach ($this->newAttachments as $file) {
                $storedPath = $file->store("tenants/{$this->tenantId}/post-its/{$postIt->id}", 'local');
                $postIt->attachments()->create([
                    'uploader_id' => $requester->id,
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $storedPath,
                    'mime_type' => $file->getClientMimeType() ?: ($file->getMimeType() ?: 'application/octet-stream'),
                    'file_size' => $file->getSize() ?: 0,
                ]);
            }
        }

        if ($isAssigned) {
            PostItTransfer::create([
                'post_it_id' => $postIt->id,
                'actor_id' => $requester->id,
                'from_user_id' => null,
                'to_user_id' => $this->assigneeId,
                'action' => 'assigned',
                'created_at' => now(),
            ]);

            $assignedWorker = User::find($this->assigneeId);
            if ($assignedWorker !== null) {
                $assignedWorker->update([
                    'current_capacity' => (int) PostIt::where('assignee_id', $assignedWorker->id)
                        ->whereIn('status', ['assigned', 'doing'])
                        ->sum('weight'),
                ]);
            }
        }

        $this->resetCreateForm();
        $this->showCreateModal = false;

        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Check if a post-it was requested by the user or originated from their facility.
     */
    public function isOwnOrSameFacility(PostIt $postIt, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($postIt->requester_id !== null && (int) $postIt->requester_id === (int) $user->id) {
            return true;
        }

        return filled($user->facility_name) && $postIt->facility_name === $user->facility_name;
    }

    /**
     * Sanitize a PostIt record based on the privacy & visibility matrix and strict client isolation.
     */
    protected function sanitizePostIt(PostIt $postIt, ?User $currentUser): void
    {
        $isOwn = $currentUser && $postIt->requester_id !== null && (int) $postIt->requester_id === (int) $currentUser->id;
        $postIt->is_own = $isOwn;

        $tenant = Tenant::find($this->tenantId);
        $strictIsolation = (bool) ($tenant?->strict_client_isolation ?? false);

        // Strict B2B Client Isolation check: foreign cards become compact anonymized slots
        if ($strictIsolation && $currentUser && $currentUser->role === 'client') {
            if (! $this->isOwnOrSameFacility($postIt, $currentUser)) {
                $postIt->is_foreign_slot = true;
                $postIt->title = __('Occupied Slot');
                $postIt->description = null;
                $postIt->display_requester_name = __('Confidential');
                $postIt->is_masked = true;
                $postIt->is_own = false;
                $postIt->display_assigned_by_name = null;

                return;
            }
        }

        $postIt->is_foreign_slot = false;
        $isInternalRequester = $postIt->requester && in_array($postIt->requester->role, ['worker', 'coordinator', 'admin'], true);

        // Open collaborator attribution:
        // Internal staff requesters are NEVER anonymized across the application.
        if (! $currentUser || $currentUser->role === 'admin' || $isInternalRequester) {
            $postIt->display_requester_name = $postIt->requesterDisplayName();
            $postIt->is_masked = false;
        } elseif ($isOwn || ($currentUser->role === 'client' && filled($currentUser->facility_name) && $postIt->facility_name === $currentUser->facility_name)) {
            $postIt->display_requester_name = $postIt->requesterDisplayName();
            $postIt->is_masked = false;
        } else {
            $postIt->display_requester_name = __('Anonymous Request');
            if ($currentUser->role === 'client') {
                $postIt->is_masked = true;
                $postIt->description = null;
            } else {
                $postIt->is_masked = false;
            }
        }

        // Assigned By attribution (openly displays who forwarded/assigned the card)
        if ($postIt->assigned_by_id && $postIt->assignedBy) {
            $postIt->display_assigned_by_name = $postIt->assignedBy->name;
        } else {
            $postIt->display_assigned_by_name = null;
        }
    }

    /**
     * Check if a user is authorized to inspect/view a post-it's details.
     */
    public function canInspectPostIt(PostIt $postIt, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        $tenant = Tenant::find($this->tenantId);
        $strictIsolation = (bool) ($tenant?->strict_client_isolation ?? false);

        if ($user->role === 'client') {
            if ($strictIsolation) {
                return $this->isOwnOrSameFacility($postIt, $user);
            }

            return (int) $postIt->requester_id === (int) $user->id;
        }

        if ((int) $postIt->requester_id === (int) $user->id) {
            return true;
        }

        if ((int) $postIt->assignee_id === (int) $user->id || (int) $postIt->helper_id === (int) $user->id) {
            return true;
        }

        if ($user->role === 'coordinator') {
            if ($postIt->status === 'backlog') {
                return true;
            }
            if ((int) $postIt->department_id === (int) $user->department_id || (int) $postIt->assignee?->department_id === (int) $user->department_id) {
                return true;
            }
        }

        if ($user->role === 'worker' && $postIt->status === 'backlog') {
            return true;
        }

        return false;
    }

    /**
     * Check if a user is authorized to drag/move a post-it.
     */
    public function canMovePostIt(PostIt $postIt, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'coordinator') {
            if ($postIt->status === 'backlog') {
                return true;
            }

            return (int) $postIt->assignee_id === (int) $user->id
                || (int) $postIt->helper_id === (int) $user->id
                || (int) $postIt->department_id === (int) $user->department_id
                || (int) $postIt->assignee?->department_id === (int) $user->department_id;
        }

        if ($user->role === 'worker') {
            if ($postIt->status === 'backlog') {
                return true;
            }

            return (int) $postIt->assignee_id === (int) $user->id || (int) $postIt->helper_id === (int) $user->id;
        }

        if ($user->role === 'client') {
            // Client can drag own unstarted cards ('backlog' or 'assigned', started_at === null)
            $isSameFacility = filled($user->facility_name) && $postIt->facility_name === $user->facility_name;

            return ((int) $postIt->requester_id === (int) $user->id || $isSameFacility)
                && in_array($postIt->status, ['backlog', 'assigned'], true)
                && $postIt->started_at === null;
        }

        return false;
    }

    /**
     * Get eligible helpers for a given post-it based on assignee's visibility mode.
     */
    public function getEligibleHelpers(?PostIt $postIt)
    {
        if (! $postIt || ! $postIt->assignee_id) {
            return collect();
        }

        $assignee = $postIt->assignee ?? User::find($postIt->assignee_id);
        if (! $assignee || $assignee->board_visibility === 'private' || $assignee->board_visibility === 'none') {
            return collect();
        }

        return User::where('tenant_id', $this->tenantId)
            ->where('id', '!=', $assignee->id)
            ->where('board_visibility', $assignee->board_visibility)
            ->orderBy('name')
            ->get();
    }

    /**
     * Open the Post-it detail modal.
     */
    public function openSlideOver(int $postItId): void
    {
        $postIt = PostIt::where('tenant_id', $this->tenantId)->find($postItId);

        if (! $postIt || ! $this->canInspectPostIt($postIt, Auth::user())) {
            return;
        }

        $this->selectedPostItId = $postIt->id;
        $this->slideOverWeight = $postIt->weight;
        $this->slideOverPriority = $postIt->priority;
        $this->slideOverHelperId = $postIt->helper_id;
        $this->slideOverHelperSplit = $postIt->helper_split > 0 ? $postIt->helper_split : 20;
        $this->newCommentBody = '';
        $this->slideOverNewAttachments = [];
        $this->isEditingDetail = false;
        $this->editTitle = $postIt->title;
        $this->editDescription = $postIt->description ?? '';
        $this->editPriority = $postIt->priority;
        $this->editWeight = $postIt->weight;
        $this->editDepartmentId = $postIt->department_id;
        $this->editAssigneeId = $postIt->assignee_id;
        $this->showSlideOver = true;
    }

    /**
     * Close the Post-it detail modal.
     */
    public function closeSlideOver(): void
    {
        $this->showSlideOver = false;
        $this->selectedPostItId = null;
        $this->newCommentBody = '';
        $this->slideOverNewAttachments = [];
        $this->isEditingDetail = false;
    }

    /**
     * Scope Calibration: change weight & priority, recalculating bounty and capacity.
     */
    public function saveScopeCalibration(): void
    {
        $this->validate([
            'slideOverWeight' => 'required|integer|min:1|max:5',
            'slideOverPriority' => 'required|in:low,normal,high,urgent',
        ]);

        if (! $this->selectedPostItId) {
            return;
        }

        $postIt = PostIt::where('tenant_id', $this->tenantId)->findOrFail($this->selectedPostItId);
        $currentUser = Auth::user();
        $isCoordinator = $currentUser && $currentUser->role === 'coordinator' && (
            (int) $currentUser->department_id === (int) $postIt->department_id ||
            (int) $currentUser->department_id === (int) $postIt->assignee?->department_id
        );
        $canCalibrate = $currentUser && ($currentUser->role === 'admin' || $isCoordinator || (int) $currentUser->id === (int) $postIt->assignee_id) && $postIt->status !== 'done';

        if (! $canCalibrate) {
            return;
        }

        $calculator = app(BountyCalculator::class);
        $newBountyPoints = $calculator->calculate(
            weight: $this->slideOverWeight,
            priority: $this->slideOverPriority,
            tenantId: $this->tenantId,
        );

        $postIt->update([
            'weight' => $this->slideOverWeight,
            'priority' => $this->slideOverPriority,
            'bounty_points' => $newBountyPoints,
        ]);

        if ($postIt->assignee_id) {
            $assignee = User::find($postIt->assignee_id);
            if ($assignee) {
                $assignee->update([
                    'current_capacity' => (int) PostIt::where('assignee_id', $assignee->id)
                        ->whereIn('status', ['assigned', 'doing'])
                        ->sum('weight'),
                ]);
            }
        }

        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Save Co-op helper and bounty split directly from the slide-over.
     */
    public function saveSlideOverCoOp(): void
    {
        $this->validate([
            'slideOverHelperSplit' => 'required|integer|min:0|max:50',
            'slideOverHelperId' => 'nullable|integer|exists:users,id',
        ]);

        if (! $this->selectedPostItId) {
            return;
        }

        $postIt = PostIt::where('tenant_id', $this->tenantId)->with('assignee')->findOrFail($this->selectedPostItId);
        $currentUser = Auth::user();
        $isCoordinator = $currentUser && $currentUser->role === 'coordinator' && (
            (int) $currentUser->department_id === (int) $postIt->department_id ||
            (int) $currentUser->department_id === (int) $postIt->assignee?->department_id
        );
        $canManage = $currentUser && ($currentUser->role === 'admin' || $isCoordinator || (int) $currentUser->id === (int) $postIt->assignee_id) && $postIt->status !== 'done';

        if (! $canManage) {
            return;
        }

        // Enforce Co-op matching & self-assignment prevention
        if ($this->slideOverHelperId !== null) {
            if ($this->slideOverHelperId === (int) $postIt->assignee_id) {
                return; // User cannot be assigned as their own helper
            }

            $helper = User::where('tenant_id', $this->tenantId)->find($this->slideOverHelperId);
            $assigneeVisibility = $postIt->assignee?->board_visibility;

            if (! $helper || $assigneeVisibility === 'private' || $helper->board_visibility !== $assigneeVisibility) {
                return; // Mode mismatch or private column
            }
        }

        $postIt->update([
            'helper_id' => $this->slideOverHelperId,
            'helper_split' => $this->slideOverHelperId !== null ? min(50, max(0, $this->slideOverHelperSplit)) : 0,
        ]);

        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Post a new micro-comment on the selected post-it.
     */
    public function addComment(): void
    {
        $this->validate([
            'newCommentBody' => 'required|string|min:1|max:1000',
        ]);

        if (! $this->selectedPostItId) {
            return;
        }

        $postIt = PostIt::where('tenant_id', $this->tenantId)->findOrFail($this->selectedPostItId);
        $currentUser = Auth::user();

        if (! $this->canInspectPostIt($postIt, $currentUser)) {
            return;
        }

        $postIt->comments()->create([
            'user_id' => $currentUser->id,
            'body' => trim($this->newCommentBody),
        ]);

        $this->newCommentBody = '';

        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Auto-save attachments as soon as upload finishes in the slide-over drawer.
     */
    public function updatedSlideOverNewAttachments(): void
    {
        $this->uploadSlideOverAttachments();
    }

    /**
     * Upload one or more attachments to the selected post-it from the slide-over drawer.
     */
    public function uploadSlideOverAttachments(): void
    {
        if (! $this->selectedPostItId) {
            return;
        }

        $postIt = PostIt::where('tenant_id', $this->tenantId)->findOrFail($this->selectedPostItId);
        $currentUser = Auth::user();

        if (! $currentUser || ! $this->canInspectPostIt($postIt, $currentUser)) {
            return;
        }

        $this->validate([
            'slideOverNewAttachments' => 'required|array|min:1|max:5',
            'slideOverNewAttachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,csv,txt,zip',
        ]);

        foreach ($this->slideOverNewAttachments as $file) {
            $path = $file->store("tenants/{$this->tenantId}/post-its/{$postIt->id}", 'local');
            $postIt->attachments()->create([
                'uploader_id' => $currentUser->id,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType() ?: ($file->getMimeType() ?: 'application/octet-stream'),
                'file_size' => $file->getSize() ?: 0,
            ]);
        }

        $this->slideOverNewAttachments = [];
        unset($this->selectedPostIt);

        BoardUpdated::dispatch($this->tenantId, $currentUser->id);
    }

    /**
     * Delete an attachment from the selected post-it.
     */
    public function deleteAttachment(int $attachmentId): void
    {
        $currentUser = Auth::user();
        if (! $currentUser) {
            return;
        }

        $attachment = PostItAttachment::whereHas('postIt', function ($q): void {
            $q->where('tenant_id', $this->tenantId);
        })->with('postIt')->findOrFail($attachmentId);

        $postIt = $attachment->postIt;

        $canDelete = $currentUser->role === 'admin'
            || (int) $currentUser->id === (int) $attachment->uploader_id
            || (int) $currentUser->id === (int) $postIt->assignee_id
            || ($currentUser->role === 'coordinator' && (
                (int) $currentUser->department_id === (int) $postIt->department_id ||
                (int) $currentUser->department_id === (int) $postIt->assignee?->department_id
            ));

        if (! $canDelete) {
            return;
        }

        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();

        unset($this->selectedPostIt);

        BoardUpdated::dispatch($this->tenantId, $currentUser->id);
    }

    /**
     * Cancel / delete a backlog request (for owner client/worker or admin).
     */
    public function cancelPostIt(): void
    {
        if (! $this->selectedPostItId) {
            return;
        }

        $postIt = PostIt::where('tenant_id', $this->tenantId)->findOrFail($this->selectedPostItId);
        $currentUser = Auth::user();

        $canCancel = $currentUser && (
            $currentUser->role === 'admin' ||
            ($postIt->status === 'backlog' && (int) $postIt->requester_id === (int) $currentUser->id)
        );

        if (! $canCancel) {
            return;
        }

        $previousAssigneeId = $postIt->assignee_id;
        $postIt->delete();

        if ($previousAssigneeId) {
            $worker = User::find($previousAssigneeId);
            if ($worker) {
                $worker->update([
                    'current_capacity' => (int) PostIt::where('assignee_id', $worker->id)
                        ->whereIn('status', ['assigned', 'doing'])
                        ->sum('weight'),
                ]);
            }
        }

        $this->closeSlideOver();
        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Check if a user is authorized to edit a post-it's properties.
     */
    public function canEditPostIt(PostIt $postIt, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        if ((int) $postIt->requester_id === (int) $user->id) {
            return true;
        }

        if ((int) $postIt->assignee_id === (int) $user->id) {
            return true;
        }

        if ($user->role === 'coordinator') {
            if ($postIt->status === 'backlog') {
                return true;
            }
            if ((int) $postIt->department_id === (int) $user->department_id || (int) $postIt->assignee?->department_id === (int) $user->department_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enter inline edit mode in the post-it detail modal.
     */
    public function startEditingDetail(): void
    {
        if (! $this->selectedPostItId) {
            return;
        }

        $postIt = PostIt::where('tenant_id', $this->tenantId)->with('assignee')->findOrFail($this->selectedPostItId);
        if (! $this->canEditPostIt($postIt, Auth::user())) {
            return;
        }

        $this->editTitle = $postIt->title;
        $this->editDescription = $postIt->description ?? '';
        $this->editPriority = $postIt->priority;
        $this->editWeight = $postIt->weight;
        $this->editDepartmentId = $postIt->department_id;
        $this->editAssigneeId = $postIt->assignee_id;
        $this->isEditingDetail = true;
    }

    /**
     * Cancel inline edit mode in the post-it detail modal.
     */
    public function cancelEditingDetail(): void
    {
        $this->isEditingDetail = false;
    }

    /**
     * Save inline edits to the post-it from the detail modal.
     */
    public function savePostItEdits(): void
    {
        $this->validate([
            'editTitle' => 'required|string|min:3|max:255',
            'editDescription' => 'nullable|string|max:1000',
            'editPriority' => 'required|in:low,normal,high,urgent',
            'editWeight' => 'required|integer|min:1|max:5',
            'editDepartmentId' => 'nullable|integer|exists:departments,id',
            'editAssigneeId' => 'nullable|integer|exists:users,id',
        ]);

        if (! $this->selectedPostItId) {
            return;
        }

        $postIt = PostIt::where('tenant_id', $this->tenantId)->with(['assignee', 'requester'])->findOrFail($this->selectedPostItId);
        $currentUser = Auth::user();

        if (! $this->canEditPostIt($postIt, $currentUser)) {
            return;
        }

        // Firewall validation: Client-originated post-its can ONLY enter public columns
        $requester = $postIt->requester ?? ($postIt->requester_id ? User::find($postIt->requester_id) : null);
        $isClientRequest = ($requester && $requester->role === 'client') || ($requester === null && $postIt->guest_name !== null);
        if ($isClientRequest && $this->editAssigneeId !== null) {
            $targetCollab = User::where('tenant_id', $this->tenantId)->find($this->editAssigneeId);
            if (! $targetCollab || $targetCollab->board_visibility !== 'public') {
                $this->addError('editAssigneeId', __('This collaborator cannot receive client requests.'));

                return;
            }
            if ($currentUser->role === 'client' && ! $targetCollab->is_available) {
                $this->addError('editAssigneeId', __('This collaborator is currently away.'));

                return;
            }
        }

        $previousAssigneeId = $postIt->assignee_id;
        $newAssigneeId = $this->editAssigneeId;

        $calculator = app(BountyCalculator::class);
        $newBountyPoints = $calculator->calculate(
            weight: $this->editWeight,
            priority: $this->editPriority,
            tenantId: $this->tenantId,
        );

        $updates = [
            'title' => $this->editTitle,
            'description' => $this->editDescription !== '' ? $this->editDescription : null,
            'priority' => $this->editPriority,
            'weight' => $this->editWeight,
            'department_id' => $this->editDepartmentId,
            'bounty_points' => $newBountyPoints,
        ];

        // Track transfer and state if assignee changed
        if ((int) $previousAssigneeId !== (int) $newAssigneeId) {
            $updates['assignee_id'] = $newAssigneeId;
            if ($newAssigneeId === null) {
                $updates['status'] = 'backlog';
                $updates['helper_id'] = null;
                $updates['helper_split'] = 0;
                $updates['started_at'] = null;

                PostItTransfer::create([
                    'post_it_id' => $postIt->id,
                    'actor_id' => $currentUser->id,
                    'from_user_id' => $previousAssigneeId,
                    'to_user_id' => null,
                    'action' => 'unassigned',
                    'created_at' => now(),
                ]);
            } else {
                $updates['assigned_by_id'] = $currentUser->id;
                if ($postIt->status === 'backlog') {
                    $updates['status'] = 'assigned';
                }

                PostItTransfer::create([
                    'post_it_id' => $postIt->id,
                    'actor_id' => $currentUser->id,
                    'from_user_id' => $previousAssigneeId,
                    'to_user_id' => $newAssigneeId,
                    'action' => $previousAssigneeId ? 'transferred' : 'assigned',
                    'created_at' => now(),
                ]);
            }
        }

        $postIt->update($updates);

        // Recalculate capacity for affected workers
        $affectedUserIds = array_unique(array_filter([$previousAssigneeId, $newAssigneeId]));
        foreach ($affectedUserIds as $userId) {
            $user = User::find($userId);
            if ($user) {
                $user->update([
                    'current_capacity' => (int) PostIt::where('assignee_id', $userId)
                        ->whereIn('status', ['assigned', 'doing'])
                        ->sum('weight'),
                ]);
            }
        }

        $this->slideOverWeight = $this->editWeight;
        $this->slideOverPriority = $this->editPriority;
        $this->isEditingDetail = false;
        unset($this->selectedPostIt);

        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Check if a user is authorized to drop a post-it into the completed column.
     * Allowed: admin, coordinator (for their dept), assigned collaborator / helper, or requester (and same-facility clients).
     */
    public function canDropIntoCompleted(PostIt $postIt, ?User $currentUser): bool
    {
        if (! $currentUser || $postIt->status === 'done') {
            return false;
        }

        if ($currentUser->role === 'admin') {
            return true;
        }

        if ($currentUser->role === 'coordinator') {
            return (int) $postIt->assignee_id === (int) $currentUser->id
                || (int) $postIt->helper_id === (int) $currentUser->id
                || (int) $postIt->department_id === (int) $currentUser->department_id
                || (int) $postIt->assignee?->department_id === (int) $currentUser->department_id
                || $postIt->department_id === null;
        }

        if ((int) $postIt->assignee_id === (int) $currentUser->id || (int) $postIt->helper_id === (int) $currentUser->id) {
            return true;
        }

        if ($currentUser->role === 'client') {
            $isSameFacility = filled($currentUser->facility_name) && $postIt->facility_name === $currentUser->facility_name;

            return ((int) $postIt->requester_id === (int) $currentUser->id || $isSameFacility)
                && in_array($postIt->status, ['backlog', 'assigned'], true)
                && $postIt->started_at === null;
        }

        if ($postIt->requester_id !== null && (int) $postIt->requester_id === (int) $currentUser->id) {
            return true;
        }

        return false;
    }

    /**
     * Drop a post-it directly into the completed column.
     * Automatically completes normally if work was in progress ('doing'),
     * or cancels with 0 bounty points if unstarted ('assigned' or 'backlog').
     */
    public function dropIntoCompleted(int $postItId): void
    {
        $currentUser = Auth::user();
        if (! $currentUser) {
            abort(403);
        }

        $postIt = PostIt::where('tenant_id', $this->tenantId)->with(['assignee', 'requester'])->findOrFail($postItId);

        if ($postIt->status === 'done') {
            return;
        }

        if (! $this->canDropIntoCompleted($postIt, $currentUser)) {
            abort(403, __('Unauthorized to complete or cancel this request.'));
        }

        $previousStatus = $postIt->status;
        $previousAssigneeId = $postIt->assignee_id;
        $wasInProgress = ($previousStatus === 'doing');

        if ($wasInProgress) {
            $postIt->update([
                'status' => 'done',
                'is_canceled' => false,
                'completed_at' => now(),
            ]);

            PostItTransfer::create([
                'post_it_id' => $postIt->id,
                'actor_id' => $currentUser->id,
                'from_user_id' => $previousAssigneeId,
                'to_user_id' => $previousAssigneeId,
                'action' => 'completed',
                'created_at' => now(),
            ]);

            $calculator = app(BountyCalculator::class);
            $calculator->awardPayout($postIt->fresh());
        } else {
            // Work had NOT started: mark canceled, award 0 points, log cancel action & comment
            $postIt->update([
                'status' => 'done',
                'is_canceled' => true,
                'completed_at' => now(),
                'bounty_points' => 0,
            ]);

            PostItTransfer::create([
                'post_it_id' => $postIt->id,
                'actor_id' => $currentUser->id,
                'from_user_id' => $previousAssigneeId,
                'to_user_id' => $previousAssigneeId,
                'action' => 'canceled',
                'created_at' => now(),
            ]);

            $cancelComment = '✖ '.__('Canceled (closed before work started) by :name', ['name' => $currentUser->name]);
            $postIt->comments()->create([
                'user_id' => $currentUser->id,
                'body' => $cancelComment,
            ]);
        }

        // Recalculate capacity for affected worker
        if ($previousAssigneeId !== null) {
            $assignee = User::find($previousAssigneeId);
            if ($assignee !== null) {
                $assignee->update([
                    'current_capacity' => (int) PostIt::where('assignee_id', $previousAssigneeId)
                        ->whereIn('status', ['assigned', 'doing'])
                        ->sum('weight'),
                ]);
            }
        }

        unset($this->selectedPostIt);
        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Complete a post-it task directly from the modal dialog.
     */
    public function completePostIt(int $postItId): void
    {
        $this->dropIntoCompleted($postItId);
        unset($this->selectedPostIt);
    }

    /**
     * Determine if the user is authorized to self-close / resolve this post-it.
     */
    public function canCloseByRequester(PostIt $postIt, ?User $currentUser): bool
    {
        if (! $currentUser || $postIt->status === 'done') {
            return false;
        }

        if ($postIt->requester_id !== null && (int) $postIt->requester_id === (int) $currentUser->id) {
            return true;
        }

        return $currentUser->role === 'client'
            && filled($currentUser->facility_name)
            && $postIt->facility_name === $currentUser->facility_name;
    }

    /**
     * Self-close/cancel an open request by its creator or client from same facility.
     */
    public function closeByRequester(int $postItId, ?string $reason = null): void
    {
        $currentUser = Auth::user();
        if (! $currentUser) {
            abort(403);
        }

        $postIt = PostIt::where('tenant_id', $this->tenantId)->findOrFail($postItId);

        if (! $this->canCloseByRequester($postIt, $currentUser)) {
            abort(403, __('Unauthorized or request already completed.'));
        }

        $wasInProgress = ($postIt->status === 'doing');
        $assigneeId = $postIt->assignee_id;
        $closureReason = trim((string) ($reason ?: $this->requesterResolutionReason));
        $effectiveReason = filled($closureReason) ? $closureReason : __('Issue resolved / No longer needed');

        if ($wasInProgress) {
            $postIt->update([
                'status' => 'done',
                'completed_at' => now(),
            ]);

            $calculator = app(BountyCalculator::class);
            $calculator->awardPayout($postIt->fresh());
        } else {
            // Not started yet (backlog or assigned): complete with 0 bounty points so unearned points are not farmed
            $postIt->update([
                'status' => 'done',
                'completed_at' => now(),
                'bounty_points' => 0,
            ]);
        }

        // Recalculate capacity for affected worker
        if ($assigneeId !== null) {
            $assignee = User::find($assigneeId);
            if ($assignee !== null) {
                $assignee->update([
                    'current_capacity' => (int) PostIt::where('assignee_id', $assigneeId)
                        ->whereIn('status', ['assigned', 'doing'])
                        ->sum('weight'),
                ]);
            }
        }

        // Audit Trail: transfer history
        PostItTransfer::create([
            'post_it_id' => $postIt->id,
            'actor_id' => $currentUser->id,
            'from_user_id' => $assigneeId,
            'to_user_id' => $assigneeId,
            'action' => 'closed_by_requester',
            'created_at' => now(),
        ]);

        // Audit Trail: automated comment
        $commentBody = '✅ '.__('Closed by requester (:name): :reason', [
            'name' => $currentUser->name,
            'reason' => $effectiveReason,
        ]);

        $postIt->comments()->create([
            'user_id' => $currentUser->id,
            'body' => $commentBody,
        ]);

        $this->requesterResolutionReason = '';
        unset($this->selectedPostIt);

        session()->flash('status', __('Request closed successfully.'));

        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Computed property for the currently selected post-it and its thread.
     */
    #[Computed]
    public function selectedPostIt(): ?PostIt
    {
        if (! $this->selectedPostItId) {
            return null;
        }

        $postIt = PostIt::where('tenant_id', $this->tenantId)
            ->with([
                'requester',
                'assignee.department',
                'assignedBy',
                'helper',
                'department',
                'transfers.actor',
                'transfers.fromUser',
                'transfers.toUser',
                'comments' => fn ($query) => $query->with('user')->oldest(),
                'attachments' => fn ($query) => $query->with('uploader')->latest('id'),
            ])
            ->find($this->selectedPostItId);

        if (! $postIt || ! $this->canInspectPostIt($postIt, Auth::user())) {
            return null;
        }

        $this->sanitizePostIt($postIt, Auth::user());

        return $postIt;
    }

    /**
     * Toggle filtering completed column to only tasks involving the current user / facility.
     */
    public function toggleFilterCompletedMineOnly(): void
    {
        $this->filterCompletedMineOnly = ! $this->filterCompletedMineOnly;
    }

    /**
     * Open the Requests History Modal.
     */
    public function openRequestsHistoryModal(): void
    {
        $this->historySearch = '';
        $this->historyStatusFilter = 'all';
        $this->showRequestsHistoryModal = true;
    }

    /**
     * Close the Requests History Modal.
     */
    public function closeRequestsHistoryModal(): void
    {
        $this->showRequestsHistoryModal = false;
        $this->historySearch = '';
        $this->historyStatusFilter = 'all';
    }

    /**
     * Open Post-it detail modal from a record in the Requests History Modal.
     */
    public function openDetailFromHistory(int $postItId): void
    {
        $this->showRequestsHistoryModal = false;
        $this->openSlideOver($postItId);
    }

    /**
     * Query requests for the Requests History Modal.
     */
    #[Computed]
    public function historyRequests()
    {
        $currentUser = Auth::user();
        if (! $currentUser) {
            return collect();
        }

        $query = PostIt::where('tenant_id', $this->tenantId)
            ->with([
                'requester',
                'assignee',
                'department',
            ]);

        // Scoping for client:
        if ($currentUser->role === 'client') {
            $query->where(function ($q) use ($currentUser): void {
                $q->where('requester_id', $currentUser->id);
                if (filled($currentUser->facility_name)) {
                    $q->orWhere('facility_name', $currentUser->facility_name);
                }
            });
        }

        // Status filter
        if ($this->historyStatusFilter === 'active') {
            $query->whereIn('status', ['backlog', 'assigned', 'doing']);
        } elseif ($this->historyStatusFilter === 'completed') {
            $query->where('status', 'done');
        }

        // Search query
        if (filled($this->historySearch)) {
            $term = '%'.trim($this->historySearch).'%';
            $query->where(function ($q) use ($term): void {
                $q->where('title', 'like', $term)
                    ->orWhere('facility_name', 'like', $term)
                    ->orWhereHas('requester', fn ($sub) => $sub->where('name', 'like', $term))
                    ->orWhereHas('assignee', fn ($sub) => $sub->where('name', 'like', $term));
            });
        }

        return $query->latest('id')->limit(100)->get();
    }

    /**
     * Render the Livewire component.
     */
    public function render(): View
    {
        $currentUser = Auth::user();

        // 1. Dual-Lens Department Radar Calculation
        // - Clients: aggregate ONLY 'public' & is_available === true columns.
        // - Internal Staff: aggregate 'public' + 'internal' & is_available === true columns (excluding private & away).
        $departments = Department::where('tenant_id', $this->tenantId)->get();

        $radarUsersQuery = User::where('tenant_id', $this->tenantId)
            ->where('is_available', true);

        if ($currentUser && $currentUser->role === 'client') {
            $radarUsersQuery->where('board_visibility', 'public');
        } else {
            $radarUsersQuery->whereIn('board_visibility', ['public', 'internal']);
        }

        $activeRadarUsers = $radarUsersQuery->get();

        $allCurrentCap = (int) $activeRadarUsers->sum('current_capacity');
        $allMaxCap = (int) $activeRadarUsers->sum('max_capacity');
        $allRatio = $allMaxCap > 0 ? (int) round(($allCurrentCap / $allMaxCap) * 100) : 0;

        $radarTabs = [
            [
                'id' => null,
                'name' => __('All Departments'),
                'color' => '#6366f1',
                'current_capacity' => $allCurrentCap,
                'max_capacity' => $allMaxCap,
                'ratio' => $allRatio,
                'status' => $allRatio >= 100 ? 'danger' : ($allRatio >= 70 ? 'warning' : 'healthy'),
            ],
        ];

        foreach ($departments as $dept) {
            $deptWorkers = $activeRadarUsers->where('department_id', $dept->id);
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

        // 2. Query Columns based on Viewer Filter
        // - client viewers ONLY see columns where board_visibility === 'public' AND is_available === true (Away workers completely hidden from clients).
        // - staff (worker, coordinator, admin) see all 'public' and 'internal' columns,
        //   PLUS their own column if board_visibility === 'private'.
        // - board_visibility === 'none' never appears as a column.
        $columnsQuery = User::where('tenant_id', $this->tenantId)
            ->with(['department', 'assignedPostIts' => function ($query): void {
                $query->whereIn('status', ['assigned', 'doing'])
                    ->smartOrder()
                    ->withCount(['comments', 'attachments'])
                    ->with([
                        'requester:id,name,role',
                        'assignee:id,name,board_visibility',
                        'helper:id,name',
                        'assignedBy:id,name',
                        'department:id,name,color',
                        'transfers.toUser:id,name',
                    ]);
            }])
            ->orderBy('name');

        if ($currentUser && $currentUser->role === 'client') {
            $columnsQuery->where('board_visibility', 'public')
                ->where('is_available', true);
        } else {
            $currentUserId = $currentUser?->id;
            $columnsQuery->where(function ($query) use ($currentUserId): void {
                $query->whereIn('board_visibility', ['public', 'internal']);
                if ($currentUserId) {
                    $query->orWhere(function ($sub) use ($currentUserId): void {
                        $sub->where('board_visibility', 'private')
                            ->where('id', $currentUserId);
                    });
                }
            });
        }

        if ($this->selectedDepartmentId !== null) {
            $columnsQuery->where('department_id', $this->selectedDepartmentId);
        }

        $workers = $columnsQuery->get();

        // 3. Query Backlog Post-Its (filtered by selected department)
        $backlogQuery = PostIt::where('tenant_id', $this->tenantId)
            ->where(function ($query): void {
                $query->where('status', 'backlog')
                    ->orWhereNull('assignee_id');
            })
            ->withCount(['comments', 'attachments'])
            ->with([
                'requester:id,name,role',
                'assignee:id,name,board_visibility',
                'helper:id,name',
                'assignedBy:id,name',
                'department:id,name,color',
                'transfers.toUser:id,name',
            ])
            ->smartOrder();

        if ($this->selectedDepartmentId !== null) {
            $backlogQuery->where('department_id', $this->selectedDepartmentId);
        }

        $tenant = Tenant::find($this->tenantId);
        $strictIsolation = (bool) ($tenant?->strict_client_isolation ?? false);

        if ($strictIsolation && $currentUser && $currentUser->role === 'client') {
            $backlogQuery->where(function ($query) use ($currentUser): void {
                $query->where('requester_id', $currentUser->id);
                if (filled($currentUser->facility_name)) {
                    $query->orWhere('facility_name', $currentUser->facility_name);
                }
            });
        }

        $backlogPostIts = $backlogQuery->get();

        // 4. Query Done Post-Its (with strict privacy visibility scoping and facility scoping)
        $doneQuery = PostIt::where('tenant_id', $this->tenantId)
            ->where('status', 'done');

        if ($this->selectedDepartmentId !== null) {
            $doneQuery->where('department_id', $this->selectedDepartmentId);
        }

        // If client: always scope to requests submitted by themselves OR their facility
        if ($currentUser && $currentUser->role === 'client') {
            $doneQuery->where(function ($query) use ($currentUser): void {
                $query->where('requester_id', $currentUser->id);
                if (filled($currentUser->facility_name)) {
                    $query->orWhere('facility_name', $currentUser->facility_name);
                }
            });
        } elseif ($currentUser && $this->filterCompletedMineOnly) {
            // Staff quick toggle for "Mine / My Facility"
            $doneQuery->where(function ($query) use ($currentUser): void {
                $query->where('requester_id', $currentUser->id)
                    ->orWhere('assignee_id', $currentUser->id)
                    ->orWhere('helper_id', $currentUser->id);
                if (filled($currentUser->facility_name)) {
                    $query->orWhere('facility_name', $currentUser->facility_name);
                }
            });
        }

        $donePostIts = $doneQuery->withCount(['comments', 'attachments'])
            ->with([
                'requester:id,name,role',
                'assignee:id,name,board_visibility',
                'helper:id,name',
                'assignedBy:id,name',
                'department:id,name,color',
                'transfers.toUser:id,name',
            ])
            ->smartOrder()
            ->get();

        // Group completed Post-its into 3 collapsible buckets: Today, This Week, Earlier
        $todayStart = now()->startOfDay();
        $weekStart = now()->startOfWeek();

        $doneToday = $donePostIts->filter(function ($item) use ($todayStart) {
            $time = $item->completed_at ?? $item->updated_at;

            return $time && $time->gte($todayStart);
        });

        $doneThisWeek = $donePostIts->filter(function ($item) use ($todayStart, $weekStart) {
            $time = $item->completed_at ?? $item->updated_at;

            return $time && $time->gte($weekStart) && $time->lt($todayStart);
        });

        $doneEarlier = $donePostIts->filter(function ($item) use ($weekStart) {
            $time = $item->completed_at ?? $item->updated_at;

            return ! $time || $time->lt($weekStart);
        });

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

        // Available collaborators for direct assignment in the create modal
        $availableQuery = User::where('tenant_id', $this->tenantId)->orderBy('name');
        if ($currentUser && $currentUser->role === 'client') {
            $availableQuery->where('board_visibility', 'public')->where('is_available', true);
        } else {
            $currentUserId = $currentUser?->id;
            $availableQuery->where(function ($query) use ($currentUserId): void {
                $query->whereIn('board_visibility', ['public', 'internal']);
                if ($currentUserId) {
                    $query->orWhere(function ($sub) use ($currentUserId): void {
                        $sub->where('board_visibility', 'private')
                            ->where('id', $currentUserId);
                    });
                }
            });
        }
        $availableCollaborators = $availableQuery->with('department')->get();

        return view('livewire.capacity-board', [
            'radarTabs' => $radarTabs,
            'departments' => $departments,
            'workers' => $workers,
            'backlogPostIts' => $backlogPostIts,
            'donePostIts' => $donePostIts,
            'doneToday' => $doneToday,
            'doneThisWeek' => $doneThisWeek,
            'doneEarlier' => $doneEarlier,
            'availableCollaborators' => $availableCollaborators,
        ])->layout('layouts.app');
    }
}
