<?php

namespace App\Livewire;

use App\Events\BoardUpdated;
use App\Livewire\Concerns\ManagesTelegramPairing;
use App\Livewire\Concerns\ManagesWorkspaceSettings;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TeamManagement extends Component
{
    use ManagesTelegramPairing;
    use ManagesWorkspaceSettings;

    public int $tenantId = 0;

    public string $tenantName = '';

    public string $activeTab = 'leaderboard'; // 'leaderboard', 'roster', 'departments'

    // Roster Filters & Search
    public string $rosterRoleFilter = 'all';

    public ?int $rosterDepartmentFilter = null;

    public string $rosterSearch = '';

    // Department Modal Properties
    public bool $showDepartmentModal = false;

    public ?int $editingDepartmentId = null;

    public string $departmentName = '';

    public string $departmentSlug = '';

    public string $departmentColor = '#6366f1';

    // User Modal Properties
    public bool $showUserModal = false;

    public ?int $editingUserId = null;

    public string $userName = '';

    public string $userEmail = '';

    public string $userPassword = '';

    public string $userRole = 'worker';

    public ?int $userDepartmentId = null;

    public int $userMaxCapacity = 10;

    public string $userVisibility = 'public';

    public string $userBoardVisibility = 'public';

    public string $userFacilityName = '';

    public bool $userIsAvailable = true;

    public string $userTimezone = '';

    public string $analyticsPeriod = '30d'; // '7d', '30d', 'month', 'all'

    /**
     * Preset Tailwind-friendly department colors.
     *
     * @var array<string, string>
     */
    public const array PRESET_COLORS = [
        '#6366f1' => 'Indigo',
        '#3b82f6' => 'Blue',
        '#06b6d4' => 'Cyan',
        '#10b981' => 'Emerald',
        '#f59e0b' => 'Amber',
        '#f43f5e' => 'Rose',
        '#8b5cf6' => 'Purple',
        '#ec4899' => 'Pink',
        '#14b8a6' => 'Teal',
        '#f97316' => 'Orange',
    ];

    /**
     * Mount the component and initialize the tenant and access control.
     */
    public function mount(): ?RedirectResponse
    {
        $currentUser = Auth::user();

        // Ensure client users cannot access team management/leaderboard
        if ($currentUser && $currentUser->role === 'client') {
            return redirect()->route('board');
        }

        $tenant = app()->bound('currentTenant')
            ? app('currentTenant')
            : ($currentUser?->tenant
                ?? Tenant::where('slug', 'hellenic-demo-ops')->first()
                ?? Tenant::firstOrFail());

        $this->tenantId = $tenant->id;
        $this->tenantName = $tenant->name;
        $this->tenantSlug = $tenant->slug;

        return null;
    }

    /**
     * Switch active navigation tab.
     */
    public function setActiveTab(string $tab): void
    {
        if ($tab === 'analytics' && ! in_array(Auth::user()?->role, ['admin', 'coordinator'], true)) {
            return;
        }

        if (in_array($tab, ['leaderboard', 'roster', 'departments', 'analytics'], true)) {
            $this->activeTab = $tab;
        }
    }

    /**
     * Auto-generate slug when department name changes (for new departments).
     */
    public function updatedDepartmentName(string $value): void
    {
        if ($this->editingDepartmentId === null || empty($this->departmentSlug)) {
            $this->departmentSlug = Str::slug($value);
        }
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

        $this->redirect(request()->header('Referer', route('team')));
    }

    /**
     * Open modal to create a new department.
     */
    public function openCreateDepartmentModal(): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        $this->reset(['editingDepartmentId', 'departmentName', 'departmentSlug']);
        $this->departmentColor = '#6366f1';
        $this->showDepartmentModal = true;
    }

    /**
     * Open modal to edit an existing department.
     */
    public function openEditDepartmentModal(int $id): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        $department = Department::where('tenant_id', $this->tenantId)->findOrFail($id);

        $this->editingDepartmentId = $department->id;
        $this->departmentName = $department->name;
        $this->departmentSlug = $department->slug;
        $this->departmentColor = $department->color;
        $this->showDepartmentModal = true;
    }

    /**
     * Save (create or update) department record.
     */
    public function saveDepartment(): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        $this->validate([
            'departmentName' => 'required|string|min:2|max:100',
            'departmentSlug' => [
                'required',
                'string',
                'min:2',
                'max:100',
                Rule::unique('departments', 'slug')
                    ->where('tenant_id', $this->tenantId)
                    ->ignore($this->editingDepartmentId),
            ],
            'departmentColor' => ['required', 'regex:/^#([a-f0-9]{6}|[a-f0-9]{3})$/i'],
        ]);

        if ($this->editingDepartmentId !== null) {
            $department = Department::where('tenant_id', $this->tenantId)->findOrFail($this->editingDepartmentId);
            $department->update([
                'name' => $this->departmentName,
                'slug' => $this->departmentSlug,
                'color' => $this->departmentColor,
            ]);
        } else {
            Department::create([
                'tenant_id' => $this->tenantId,
                'name' => $this->departmentName,
                'slug' => $this->departmentSlug,
                'color' => $this->departmentColor,
            ]);
        }

        $this->reset(['showDepartmentModal', 'editingDepartmentId', 'departmentName', 'departmentSlug']);
        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Delete a department record.
     */
    public function deleteDepartment(int $id): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        $department = Department::where('tenant_id', $this->tenantId)->findOrFail($id);

        // Disassociate users belonging to this department
        User::where('tenant_id', $this->tenantId)
            ->where('department_id', $department->id)
            ->update(['department_id' => null]);

        $department->delete();

        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Keep userVisibility and userBoardVisibility synchronized when updated via Livewire.
     */
    public function updatedUserVisibility(string $value): void
    {
        $this->userBoardVisibility = $value;
    }

    /**
     * Keep userVisibility and userBoardVisibility synchronized when updated via Livewire.
     */
    public function updatedUserBoardVisibility(string $value): void
    {
        $this->userVisibility = $value;
    }

    /**
     * Open modal to create a new team member.
     */
    public function openCreateUserModal(): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        $this->reset(['editingUserId', 'userName', 'userEmail', 'userPassword', 'userDepartmentId', 'userFacilityName', 'userTimezone']);
        $this->userRole = 'worker';
        $this->userMaxCapacity = 10;
        $this->userVisibility = 'public';
        $this->userBoardVisibility = 'public';
        $this->userFacilityName = '';
        $tenant = Tenant::find($this->tenantId);
        $this->userTimezone = $tenant?->timezone ?: 'Europe/Athens';
        $this->userIsAvailable = true;
        $this->showUserModal = true;
    }

    /**
     * Open modal to edit an existing user.
     */
    public function openEditUserModal(int $id): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        $user = User::where('tenant_id', $this->tenantId)->findOrFail($id);

        $this->editingUserId = $user->id;
        $this->userName = $user->name;
        $this->userEmail = $user->email;
        $this->userPassword = '';
        $this->userRole = $user->role;
        $this->userFacilityName = $user->facility_name ?? '';
        $this->userDepartmentId = $user->department_id;
        $this->userMaxCapacity = $user->max_capacity;
        $this->userVisibility = $user->visibility ?: ($user->board_visibility ?: 'public');
        $this->userBoardVisibility = $this->userVisibility;
        $tenant = Tenant::find($this->tenantId);
        $this->userTimezone = $user->timezone ?: ($tenant?->timezone ?: 'Europe/Athens');
        $this->userIsAvailable = (bool) $user->is_available;
        $this->showUserModal = true;
    }

    /**
     * Save (create or update) user record.
     */
    public function saveUser(): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        $rules = [
            'userName' => 'required|string|min:2|max:255',
            'userEmail' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->editingUserId),
            ],
            'userRole' => 'required|in:worker,coordinator,client,admin',
            'userFacilityName' => 'nullable|string|max:255',
            'userDepartmentId' => 'nullable|integer|exists:departments,id',
            'userMaxCapacity' => 'required|integer|min:1|max:50',
            'userVisibility' => 'nullable|in:public,internal,private,none',
            'userBoardVisibility' => 'nullable|in:public,internal,private,none',
            'userTimezone' => 'nullable|string|timezone:all',
            'userIsAvailable' => 'boolean',
        ];

        if ($this->editingUserId === null) {
            $rules['userPassword'] = ['required', 'string', Password::defaults()];
        } else {
            $rules['userPassword'] = ['nullable', 'string', Password::defaults()];
        }

        $this->validate($rules);

        $tenant = Tenant::findOrFail($this->tenantId);
        $isWorkerRole = in_array($this->userRole, ['admin', 'coordinator', 'worker', 'collaborator'], true);

        if ($this->editingUserId === null) {
            if ($isWorkerRole && ! $tenant->canAddWorker()) {
                $this->addError('userRole', __('Your :plan workspace has reached its limit of :max active team workers. Please upgrade to Cloud Business for unlimited capacity.', [
                    'plan' => 'Cloud Team',
                    'max' => $tenant->maxWorkersLimit(),
                ]));

                return;
            }
        } else {
            $existingUser = User::where('tenant_id', $this->tenantId)->findOrFail($this->editingUserId);
            $wasClient = ($existingUser->role === 'client');
            if ($wasClient && $isWorkerRole && ! $tenant->canAddWorker()) {
                $this->addError('userRole', __('Your :plan workspace has reached its limit of :max active team workers. Please upgrade to Cloud Business for unlimited capacity.', [
                    'plan' => 'Cloud Team',
                    'max' => $tenant->maxWorkersLimit(),
                ]));

                return;
            }
        }

        $visibility = $this->userVisibility ?: ($this->userBoardVisibility ?: 'public');

        $data = [
            'name' => $this->userName,
            'email' => $this->userEmail,
            'role' => $this->userRole,
            'facility_name' => filled($this->userFacilityName) ? trim($this->userFacilityName) : null,
            'department_id' => $this->userDepartmentId,
            'max_capacity' => $this->userMaxCapacity,
            'visibility' => $visibility,
            'board_visibility' => $visibility,
            'timezone' => filled($this->userTimezone) ? $this->userTimezone : ($tenant->timezone ?: 'Europe/Athens'),
            'is_available' => $this->userIsAvailable,
        ];

        if (! empty($this->userPassword)) {
            $data['password'] = Hash::make($this->userPassword);
        }

        if ($this->editingUserId === null) {
            $data['tenant_id'] = $this->tenantId;
            $data['current_capacity'] = 0;
            $data['total_points'] = 0;
            User::create($data);
        } else {
            $user = User::where('tenant_id', $this->tenantId)->findOrFail($this->editingUserId);
            $user->update($data);
        }

        $this->reset([
            'showUserModal',
            'editingUserId',
            'userName',
            'userEmail',
            'userPassword',
            'userFacilityName',
            'userRole',
            'userDepartmentId',
            'userMaxCapacity',
            'userVisibility',
            'userBoardVisibility',
            'userTimezone',
            'userIsAvailable',
        ]);
        $this->userRole = 'worker';
        $this->userMaxCapacity = 10;
        $this->userVisibility = 'public';
        $this->userBoardVisibility = 'public';
        $this->userIsAvailable = true;
        $this->resetValidation();

        $this->dispatch('$refresh');
        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Alias for saveUser for compatibility.
     */
    public function saveMember(): void
    {
        $this->saveUser();
    }

    /**
     * Alias for openCreateUserModal for compatibility.
     */
    public function createMember(): void
    {
        $this->openCreateUserModal();
    }

    /**
     * Alias for openEditUserModal for compatibility.
     */
    public function updateMember(int $id): void
    {
        $this->openEditUserModal($id);
    }

    /**
     * Quick toggle collaborator working/away state from roster table.
     */
    public function quickToggleAvailability(int $userId): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        $user = User::where('tenant_id', $this->tenantId)->findOrFail($userId);
        $user->update(['is_available' => ! $user->is_available]);

        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Quick update collaborator board visibility mode from roster table.
     */
    public function quickUpdateVisibility(int $userId, string $visibility): void
    {
        abort_unless(in_array($visibility, ['public', 'internal', 'private', 'none'], true), 422);

        $currentUser = Auth::user();
        abort_unless($currentUser !== null, 403);

        $user = User::where('tenant_id', $this->tenantId)->findOrFail($userId);
        abort_unless($currentUser->canChangeBoardVisibilityOf($user), 403);

        $user->update([
            'visibility' => $visibility,
            'board_visibility' => $visibility,
        ]);

        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Quick-adjust a worker's max capacity from the roster table (+/-).
     */
    public function quickAdjustCapacity(int $userId, int $delta): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        $user = User::where('tenant_id', $this->tenantId)->findOrFail($userId);
        $newCapacity = max(1, min(50, $user->max_capacity + $delta));

        $user->update(['max_capacity' => $newCapacity]);
        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Delete a user record (preventing self-deletion).
     */
    public function deleteUser(int $id): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);
        abort_if((int) $id === (int) Auth::id(), 403, 'You cannot delete your own admin account.');

        $user = User::where('tenant_id', $this->tenantId)->findOrFail($id);
        $user->delete();

        BoardUpdated::dispatch($this->tenantId);
    }

    /**
     * Log out current user.
     */
    public function logout(): mixed
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Set the selected analytics period filter.
     */
    public function setAnalyticsPeriod(string $period): void
    {
        if (in_array($period, ['7d', '30d', 'month', 'all'], true)) {
            $this->analyticsPeriod = $period;
        }
    }

    /**
     * Export completed/canceled SLA requests as a CSV download.
     */
    public function exportAnalyticsCsv(): mixed
    {
        abort_unless(in_array(Auth::user()?->role, ['admin', 'coordinator'], true), 403);

        $tenant = Tenant::findOrFail($this->tenantId);
        $tz = $tenant->timezone ?: config('app.timezone', 'Europe/Athens');

        $now = now()->timezone($tz);
        $startDate = match ($this->analyticsPeriod) {
            '7d' => $now->copy()->subDays(7)->startOfDay()->utc(),
            'month' => $now->copy()->startOfMonth()->utc(),
            'all' => null,
            default => $now->copy()->subDays(30)->startOfDay()->utc(),
        };

        $query = PostIt::where('tenant_id', $tenant->id)
            ->where('status', 'done')
            ->with(['department', 'assignee', 'helper'])
            ->orderByDesc('completed_at');

        if ($startDate !== null) {
            $query->where('created_at', '>=', $startDate);
        }

        $records = $query->get();

        $csvRows = [];
        foreach ($records as $p) {
            $waitMins = ($p->started_at && $p->started_at >= $p->created_at)
                ? (int) $p->created_at->diffInMinutes($p->started_at)
                : '-';

            $resMins = ($p->started_at && $p->completed_at && $p->completed_at >= $p->started_at && ! $p->is_canceled)
                ? (int) $p->started_at->diffInMinutes($p->completed_at)
                : '-';

            $bounty = $p->is_canceled ? 0 : (int) $p->bounty_points;

            $csvRows[] = [
                $p->tracking_token ?: ('TNT-'.$p->id),
                $p->title,
                $p->facility_name ?: 'Unassigned / Direct',
                $p->department?->name ?: 'General',
                $p->assignee?->name ?: 'Unassigned',
                ucfirst((string) $p->priority),
                'W'.$p->weight,
                $p->is_canceled ? 'Canceled / Self-Resolved' : 'Completed',
                $p->created_at ? $p->created_at->timezone($tz)->format('Y-m-d H:i:s') : '-',
                $p->started_at ? $p->started_at->timezone($tz)->format('Y-m-d H:i:s') : '-',
                $p->completed_at ? $p->completed_at->timezone($tz)->format('Y-m-d H:i:s') : '-',
                $waitMins,
                $resMins,
                $bounty,
            ];
        }

        $filename = "tantou-analytics-{$tenant->slug}-{$this->analyticsPeriod}.csv";

        return response()->streamDownload(function () use ($csvRows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            // UTF-8 BOM
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Tracking Code',
                'Title',
                'Facility',
                'Department',
                'Assignee',
                'Priority',
                'Weight',
                'Status/Resolution',
                'Created At',
                'Started At',
                'Completed At',
                'Wait Time (mins)',
                'Resolution Time (mins)',
                'Bounty Points',
            ]);
            foreach ($csvRows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Format minutes into human-readable duration (e.g. 34m, 1h 15m, 2d 4h).
     */
    protected function formatDurationMinutes(?float $minutes): string
    {
        if ($minutes === null || $minutes < 0) {
            return '-';
        }

        $totalMins = (int) round($minutes);
        if ($totalMins < 60) {
            return $totalMins.'m';
        }

        $hours = intdiv($totalMins, 60);
        $remMins = $totalMins % 60;

        if ($hours < 24) {
            return $remMins > 0 ? "{$hours}h {$remMins}m" : "{$hours}h";
        }

        $days = intdiv($hours, 24);
        $remHours = $hours % 24;

        return $remHours > 0 ? "{$days}d {$remHours}h" : "{$days}d";
    }

    /**
     * Compute Executive SLA KPIs, Department Throughput, Facility Breakdown, and Worker Efficiency.
     */
    protected function computeAnalyticsData(Tenant $tenant): array
    {
        $tz = $tenant->timezone ?: config('app.timezone', 'Europe/Athens');
        $now = now()->timezone($tz);

        $startDate = match ($this->analyticsPeriod) {
            '7d' => $now->copy()->subDays(7)->startOfDay()->utc(),
            'month' => $now->copy()->startOfMonth()->utc(),
            'all' => null,
            default => $now->copy()->subDays(30)->startOfDay()->utc(),
        };

        $query = PostIt::where('tenant_id', $tenant->id)
            ->with(['department', 'assignee', 'helper']);

        if ($startDate !== null) {
            $query->where('created_at', '>=', $startDate);
        }

        $postIts = $query->get();

        // 1. Executive SLA KPI Cards
        // MTTA: created_at -> started_at
        $startedTasks = $postIts->filter(function (PostIt $p): bool {
            return $p->started_at !== null && $p->started_at >= $p->created_at;
        });

        $mttaMinutes = $startedTasks->isNotEmpty()
            ? $startedTasks->avg(fn (PostIt $p): int => (int) $p->created_at->diffInMinutes($p->started_at))
            : null;

        // MTTR: started_at -> completed_at for non-canceled completed tasks
        $resolvedTasks = $postIts->filter(function (PostIt $p): bool {
            return $p->status === 'done'
                && ! $p->is_canceled
                && $p->started_at !== null
                && $p->completed_at !== null
                && $p->completed_at >= $p->started_at;
        });

        $mttrMinutes = $resolvedTasks->isNotEmpty()
            ? $resolvedTasks->avg(fn (PostIt $p): int => (int) $p->started_at->diffInMinutes($p->completed_at))
            : null;

        // Completion vs Cancellation
        $totalDone = $postIts->where('status', 'done')->count();
        $completedCount = $postIts->where('status', 'done')->where('is_canceled', false)->count();
        $canceledCount = $postIts->where('status', 'done')->where('is_canceled', true)->count();
        $completionRate = $totalDone > 0 ? (float) round(($completedCount / $totalDone) * 100, 1) : 0.0;
        $cancellationRate = $totalDone > 0 ? (float) round(($canceledCount / $totalDone) * 100, 1) : 0.0;

        // Urgent ratio
        $totalRequests = $postIts->count();
        $urgentHighCount = $postIts->whereIn('priority', ['urgent', 'high'])->count();
        $urgentRatio = $totalRequests > 0 ? (float) round(($urgentHighCount / $totalRequests) * 100, 1) : 0.0;

        // 2. Department Throughput & Bottlenecks
        $departments = Department::where('tenant_id', $tenant->id)->orderBy('name')->get();
        $departmentStats = [];

        foreach ($departments as $dept) {
            $deptItems = $postIts->where('department_id', $dept->id);
            $deptTotal = $deptItems->count();
            $deptQueue = $deptItems->whereIn('status', ['backlog', 'assigned', 'doing'])->count();
            $deptCompleted = $deptItems->where('status', 'done')->where('is_canceled', false)->count();
            $deptBounty = (int) $deptItems->where('status', 'done')->where('is_canceled', false)->sum('bounty_points');

            $deptResolved = $deptItems->filter(function (PostIt $p): bool {
                return $p->status === 'done'
                    && ! $p->is_canceled
                    && $p->started_at !== null
                    && $p->completed_at !== null
                    && $p->completed_at >= $p->started_at;
            });

            $deptMttr = $deptResolved->isNotEmpty()
                ? $deptResolved->avg(fn (PostIt $p): int => (int) $p->started_at->diffInMinutes($p->completed_at))
                : null;

            $departmentStats[] = [
                'id' => $dept->id,
                'name' => $dept->name,
                'color' => $dept->color,
                'totalRequests' => $deptTotal,
                'activeQueue' => $deptQueue,
                'completedTasks' => $deptCompleted,
                'totalBounty' => $deptBounty,
                'mttrMinutes' => $deptMttr,
                'mttrFormatted' => $this->formatDurationMinutes($deptMttr),
                'completionRate' => $deptTotal > 0 ? (int) round(($deptCompleted / $deptTotal) * 100) : 0,
            ];
        }

        // 3. Top Requesting Facilities / Client Units (facility_name Breakdown)
        $facilityGroups = $postIts->groupBy(function (PostIt $p): string {
            return filled($p->facility_name) ? (string) $p->facility_name : 'Unassigned / Direct';
        });

        $totalTenantWeight = (int) $postIts->sum('weight');
        $facilityStats = [];

        foreach ($facilityGroups as $facilityName => $group) {
            $facWeight = (int) $group->sum('weight');
            $facilityStats[] = [
                'name' => $facilityName,
                'totalRequests' => $group->count(),
                'urgentCount' => $group->whereIn('priority', ['urgent', 'high'])->count(),
                'canceledCount' => $group->where('status', 'done')->where('is_canceled', true)->count(),
                'totalWeight' => $facWeight,
                'weightPercentage' => $totalTenantWeight > 0 ? (float) round(($facWeight / $totalTenantWeight) * 100, 1) : 0.0,
            ];
        }

        usort($facilityStats, fn (array $a, array $b): int => $b['totalWeight'] <=> $a['totalWeight']);

        // 4. Worker SLA & Co-op Efficiency Breakdown
        $workers = User::where('tenant_id', $tenant->id)
            ->whereIn('role', ['worker', 'coordinator'])
            ->with('department')
            ->orderBy('name')
            ->get();

        $workerStats = [];
        foreach ($workers as $worker) {
            $soloDone = $postIts->where('status', 'done')
                ->where('is_canceled', false)
                ->where('assignee_id', $worker->id)
                ->whereNull('helper_id')
                ->count();

            $coopDone = $postIts->where('status', 'done')
                ->where('is_canceled', false)
                ->where('helper_id', $worker->id)
                ->count();

            // Calculate period bounty earned
            $earnedPoints = 0;
            // As assignee
            $assigneeTasks = $postIts->where('status', 'done')
                ->where('is_canceled', false)
                ->where('assignee_id', $worker->id);

            foreach ($assigneeTasks as $t) {
                if ($t->helper_id !== null && $t->helper_split > 0) {
                    $earnedPoints += (int) round($t->bounty_points * (1 - ($t->helper_split / 100)));
                } else {
                    $earnedPoints += (int) $t->bounty_points;
                }
            }

            // As helper
            $helperTasks = $postIts->where('status', 'done')
                ->where('is_canceled', false)
                ->where('helper_id', $worker->id);

            foreach ($helperTasks as $t) {
                $earnedPoints += (int) round($t->bounty_points * ($t->helper_split / 100));
            }

            // Worker MTTR
            $wResolved = $postIts->filter(function (PostIt $p) use ($worker): bool {
                return $p->status === 'done'
                    && ! $p->is_canceled
                    && $p->assignee_id === $worker->id
                    && $p->started_at !== null
                    && $p->completed_at !== null
                    && $p->completed_at >= $p->started_at;
            });

            $wMttr = $wResolved->isNotEmpty()
                ? $wResolved->avg(fn (PostIt $p): int => (int) $p->started_at->diffInMinutes($p->completed_at))
                : null;

            $workerStats[] = [
                'id' => $worker->id,
                'name' => $worker->name,
                'role' => $worker->role,
                'department' => $worker->department?->name,
                'departmentColor' => $worker->department?->color,
                'soloCompleted' => $soloDone,
                'coopAssists' => $coopDone,
                'bountyEarned' => $earnedPoints,
                'mttrMinutes' => $wMttr,
                'mttrFormatted' => $this->formatDurationMinutes($wMttr),
            ];
        }

        usort($workerStats, fn (array $a, array $b): int => $b['bountyEarned'] <=> $a['bountyEarned']);

        return [
            'period' => $this->analyticsPeriod,
            'kpis' => [
                'mttaMinutes' => $mttaMinutes,
                'mttaFormatted' => $this->formatDurationMinutes($mttaMinutes),
                'startedCount' => $startedTasks->count(),
                'mttrMinutes' => $mttrMinutes,
                'mttrFormatted' => $this->formatDurationMinutes($mttrMinutes),
                'resolvedCount' => $resolvedTasks->count(),
                'totalDone' => $totalDone,
                'completedCount' => $completedCount,
                'canceledCount' => $canceledCount,
                'completionRate' => $completionRate,
                'cancellationRate' => $cancellationRate,
                'totalRequests' => $totalRequests,
                'urgentHighCount' => $urgentHighCount,
                'urgentRatio' => $urgentRatio,
            ],
            'departments' => $departmentStats,
            'facilities' => $facilityStats,
            'workers' => $workerStats,
        ];
    }

    /**
     * Render the Livewire component.
     */
    public function render(): View
    {
        $tenant = Tenant::findOrFail($this->tenantId);

        if ($this->activeTab === 'analytics' && ! in_array(Auth::user()?->role, ['admin', 'coordinator'], true)) {
            $this->activeTab = 'leaderboard';
        }

        // 1. Departments with worker counts and live aggregate capacity
        $departments = Department::where('tenant_id', $this->tenantId)
            ->with(['users' => function ($query): void {
                $query->whereIn('role', ['worker', 'coordinator']);
            }])
            ->orderBy('name')
            ->get();

        foreach ($departments as $dept) {
            $workers = $dept->users;
            $currentCap = (int) $workers->sum('current_capacity');
            $maxCap = (int) $workers->sum('max_capacity');
            $ratio = $maxCap > 0 ? (int) round(($currentCap / $maxCap) * 100) : 0;

            $dept->worker_count = $workers->count();
            $dept->current_capacity = $currentCap;
            $dept->max_capacity = $maxCap;
            $dept->ratio = $ratio;
            $dept->status = $ratio >= 100 ? 'danger' : ($ratio >= 70 ? 'warning' : 'healthy');
        }

        // 2. Tantou Gamification & Co-op Leaderboard (workers + coordinators)
        $leaderboardWorkers = User::where('tenant_id', $this->tenantId)
            ->whereIn('role', ['worker', 'coordinator'])
            ->with('department')
            ->withCount([
                'assignedPostIts as completed_tantou_count' => function ($query): void {
                    $query->where('status', 'done');
                },
                'helpedPostIts as completed_coop_count' => function ($query): void {
                    $query->where('status', 'done');
                },
            ])
            ->orderByDesc('total_points')
            ->orderBy('name')
            ->get();

        // 3. Team Roster Query with filters
        $rosterQuery = User::where('tenant_id', $this->tenantId)
            ->with('department')
            ->orderBy('role')
            ->orderBy('name');

        if ($this->rosterRoleFilter !== 'all') {
            $rosterQuery->where('role', $this->rosterRoleFilter);
        }

        if ($this->rosterDepartmentFilter !== null) {
            $rosterQuery->where('department_id', $this->rosterDepartmentFilter);
        }

        if ($this->rosterSearch !== '') {
            $term = '%'.$this->rosterSearch.'%';
            $rosterQuery->where(function ($query) use ($term): void {
                $query->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        $rosterUsers = $rosterQuery->get();

        $analyticsData = $this->activeTab === 'analytics'
            ? $this->computeAnalyticsData($tenant)
            : [];

        return view('livewire.team-management', [
            'tenant' => $tenant,
            'departments' => $departments,
            'leaderboardWorkers' => $leaderboardWorkers,
            'rosterUsers' => $rosterUsers,
            'presetColors' => self::PRESET_COLORS,
            'analytics' => $analyticsData,
        ])->layout('layouts.app');
    }
}
