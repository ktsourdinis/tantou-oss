<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class SuperadminDashboard extends Component
{
    use WithPagination;

    public string $search = '';

    public string $planFilter = '';

    public string $statusFilter = '';

    public int $perPage = 15;

    // Edit Tenant Modal State
    public bool $showEditModal = false;

    public ?int $editingTenantId = null;

    public string $editingTenantName = '';

    public string $editingPlan = 'team';

    public string $editingStatus = 'active';

    public ?string $editingCustomDomain = null;

    // Create Tenant Modal State
    public bool $showCreateModal = false;

    public string $newTenantName = '';

    public string $newTenantSlug = '';

    public string $newTenantPlan = 'team';

    public string $newTenantStatus = 'active';

    public string $newAdminName = '';

    public string $newAdminEmail = '';

    public string $newAdminPassword = '';

    public string $newAdminPassword_confirmation = '';

    /**
     * Reset pagination when search query or filters change.
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPlanFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Auto-slug generation for new tenant.
     */
    public function updatedNewTenantName(string $value): void
    {
        if (empty($this->newTenantSlug)) {
            $this->newTenantSlug = Str::slug($value);
        }
    }

    /**
     * Impersonate primary tenant admin.
     */
    public function impersonate(int $tenantId): mixed
    {
        $tenant = Tenant::findOrFail($tenantId);

        $admin = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('role', 'admin')
            ->orderBy('id')
            ->first();

        if (! $admin) {
            $admin = User::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->orderBy('id')
                ->first();
        }

        if (! $admin) {
            session()->flash('error', __('No admin or member user found in this workspace to impersonate.'));

            return null;
        }

        session([
            'impersonator_id' => auth()->id(),
            'active_tenant_id' => $tenant->id,
        ]);

        Auth::login($admin);

        $targetUrl = Edition::workspaceUrl($tenant, '/board');

        return redirect()->to($targetUrl);
    }

    /**
     * Open inline edit modal for a tenant.
     */
    public function openEditTenantModal(int $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);

        $this->editingTenantId = $tenant->id;
        $this->editingTenantName = $tenant->name;
        $this->editingPlan = $tenant->plan ?: 'team';
        $this->editingStatus = $tenant->subscription_status ?: 'active';
        $this->editingCustomDomain = $tenant->custom_domain;
        $this->showEditModal = true;
    }

    /**
     * Close edit modal.
     */
    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editingTenantId = null;
        $this->resetValidation();
    }

    /**
     * Save updated tenant plan, status, and custom domain.
     */
    public function saveTenantEdits(): void
    {
        $this->validate([
            'editingPlan' => ['required', 'in:team,business'],
            'editingStatus' => ['required', 'in:active,trialing,past_due,suspended'],
            'editingCustomDomain' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('tenants', 'custom_domain')->ignore($this->editingTenantId),
            ],
        ]);

        $tenant = Tenant::findOrFail($this->editingTenantId);

        $cleanDomain = filled($this->editingCustomDomain)
            ? strtolower(trim(preg_replace('#^https?://#', '', $this->editingCustomDomain)))
            : null;

        $tenant->update([
            'plan' => $this->editingPlan,
            'subscription_status' => $this->editingStatus,
            'custom_domain' => $cleanDomain,
        ]);

        $this->showEditModal = false;
        $this->editingTenantId = null;

        session()->flash('status', __('Workspace :name updated successfully.', ['name' => $tenant->name]));
    }

    /**
     * Open modal to provision a brand-new tenant organization.
     */
    public function openCreateTenantModal(): void
    {
        $this->reset([
            'newTenantName',
            'newTenantSlug',
            'newTenantPlan',
            'newTenantStatus',
            'newAdminName',
            'newAdminEmail',
            'newAdminPassword',
            'newAdminPassword_confirmation',
        ]);

        $this->newTenantPlan = 'team';
        $this->newTenantStatus = 'active';
        $this->showCreateModal = true;
    }

    /**
     * Close create tenant modal.
     */
    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetValidation();
    }

    /**
     * Provision a brand-new tenant and its initial admin user.
     */
    public function createTenant(): void
    {
        $this->validate([
            'newTenantName' => ['required', 'string', 'min:2', 'max:100'],
            'newTenantSlug' => ['required', 'string', 'alpha_dash', 'min:2', 'max:50', 'unique:tenants,slug'],
            'newTenantPlan' => ['required', 'in:team,business'],
            'newTenantStatus' => ['required', 'in:active,trialing,past_due,suspended'],
            'newAdminName' => ['required', 'string', 'min:2', 'max:100'],
            'newAdminEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'newAdminPassword' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $slug = Str::slug($this->newTenantSlug);

        DB::transaction(function () use ($slug) {
            $tenant = Tenant::create([
                'name' => trim($this->newTenantName),
                'slug' => $slug,
                'plan' => $this->newTenantPlan,
                'billing_interval' => 'monthly',
                'subscription_status' => $this->newTenantStatus,
                'trial_ends_at' => $this->newTenantStatus === 'trialing' ? now()->addDays(14) : null,
                'settings' => [
                    'theme' => 'dark',
                    'timezone' => 'Europe/Athens',
                ],
                'timezone' => 'Europe/Athens',
            ]);

            $dept = Department::create([
                'tenant_id' => $tenant->id,
                'name' => 'Operations',
                'slug' => 'operations',
                'color' => '#6366f1',
            ]);

            User::create([
                'tenant_id' => $tenant->id,
                'department_id' => $dept->id,
                'name' => trim($this->newAdminName),
                'email' => Str::lower(trim($this->newAdminEmail)),
                'password' => Hash::make($this->newAdminPassword),
                'role' => 'admin',
                'board_visibility' => 'public',
                'visibility' => 'public',
                'is_available' => true,
                'max_capacity' => 15,
                'timezone' => 'Europe/Athens',
            ]);
        });

        $this->showCreateModal = false;

        session()->flash('status', __('New organization workspace provisioned successfully!'));
    }

    public function render(): View
    {
        // 1. Platform KPI Metrics across all tenants
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('subscription_status', 'active')->count();
        $trialingTenants = Tenant::where('subscription_status', 'trialing')->count();
        $suspendedTenants = Tenant::whereIn('subscription_status', ['suspended', 'past_due'])->count();

        $businessTenants = Tenant::where('plan', 'business')->count();
        $teamTenants = Tenant::where('plan', 'team')->count();
        $estimatedMrr = ($teamTenants * 29) + ($businessTenants * 79);

        $totalUsers = User::withoutGlobalScopes()->count();
        $workerUsers = User::withoutGlobalScopes()->whereIn('role', ['admin', 'coordinator', 'collaborator', 'worker'])->count();
        $clientUsers = User::withoutGlobalScopes()->where('role', 'client')->count();

        $totalPostIts = PostIt::withoutGlobalScopes()->count();
        $activePostIts = PostIt::withoutGlobalScopes()->whereIn('status', ['backlog', 'assigned', 'doing'])->count();
        $donePostIts = PostIt::withoutGlobalScopes()->where('status', 'done')->count();

        // 2. Tenants Table Query
        $tenantsQuery = Tenant::query()
            ->withCount([
                'users as workers_count' => fn ($q) => $q->withoutGlobalScopes()->whereIn('role', ['admin', 'coordinator', 'collaborator', 'worker']),
                'users as clients_count' => fn ($q) => $q->withoutGlobalScopes()->where('role', 'client'),
                'postIts as total_tasks_count' => fn ($q) => $q->withoutGlobalScopes(),
            ]);

        if (filled($this->search)) {
            $term = '%'.strtolower(trim($this->search)).'%';
            $tenantsQuery->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(custom_domain) LIKE ?', [$term]);
            });
        }

        if (filled($this->planFilter)) {
            $tenantsQuery->where('plan', $this->planFilter);
        }

        if (filled($this->statusFilter)) {
            $tenantsQuery->where('subscription_status', $this->statusFilter);
        }

        $tenants = $tenantsQuery->orderByDesc('id')->paginate($this->perPage);

        return view('livewire.superadmin-dashboard', [
            'totalTenants' => $totalTenants,
            'activeTenants' => $activeTenants,
            'trialingTenants' => $trialingTenants,
            'suspendedTenants' => $suspendedTenants,
            'businessTenants' => $businessTenants,
            'teamTenants' => $teamTenants,
            'estimatedMrr' => $estimatedMrr,
            'totalUsers' => $totalUsers,
            'workerUsers' => $workerUsers,
            'clientUsers' => $clientUsers,
            'totalPostIts' => $totalPostIts,
            'activePostIts' => $activePostIts,
            'donePostIts' => $donePostIts,
            'tenants' => $tenants,
        ]);
    }
}
