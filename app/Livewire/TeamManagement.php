<?php

namespace App\Livewire;

use App\Events\BoardUpdated;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TeamManagement extends Component
{
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

    public string $userBoardVisibility = 'public';

    public bool $userIsAvailable = true;

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

        return null;
    }

    /**
     * Switch active navigation tab.
     */
    public function setActiveTab(string $tab): void
    {
        if (in_array($tab, ['leaderboard', 'roster', 'departments'], true)) {
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
     * Open modal to create a new team member.
     */
    public function openCreateUserModal(): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        $this->reset(['editingUserId', 'userName', 'userEmail', 'userPassword', 'userDepartmentId']);
        $this->userRole = 'worker';
        $this->userMaxCapacity = 10;
        $this->userBoardVisibility = 'public';
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
        $this->userDepartmentId = $user->department_id;
        $this->userMaxCapacity = $user->max_capacity;
        $this->userBoardVisibility = $user->board_visibility;
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
            'userDepartmentId' => 'nullable|integer|exists:departments,id',
            'userMaxCapacity' => 'required|integer|min:1|max:50',
            'userBoardVisibility' => 'required|in:public,internal,private,none',
            'userIsAvailable' => 'boolean',
        ];

        if ($this->editingUserId === null) {
            $rules['userPassword'] = 'required|string|min:6';
        } else {
            $rules['userPassword'] = 'nullable|string|min:6';
        }

        $this->validate($rules);

        $data = [
            'name' => $this->userName,
            'email' => $this->userEmail,
            'role' => $this->userRole,
            'department_id' => $this->userDepartmentId,
            'max_capacity' => $this->userMaxCapacity,
            'board_visibility' => $this->userBoardVisibility,
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

        $this->reset(['showUserModal', 'editingUserId', 'userName', 'userEmail', 'userPassword']);
        BoardUpdated::dispatch($this->tenantId);
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
        abort_unless(Auth::user()?->role === 'admin', 403);
        abort_unless(in_array($visibility, ['public', 'internal', 'private', 'none'], true), 422);

        $user = User::where('tenant_id', $this->tenantId)->findOrFail($userId);
        $user->update(['board_visibility' => $visibility]);

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
     * Render the Livewire component.
     */
    public function render(): View
    {
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

        return view('livewire.team-management', [
            'departments' => $departments,
            'leaderboardWorkers' => $leaderboardWorkers,
            'rosterUsers' => $rosterUsers,
            'presetColors' => self::PRESET_COLORS,
        ])->layout('layouts.app');
    }
}
