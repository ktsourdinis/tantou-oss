<?php

namespace App\Livewire\Auth;

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Register extends Component
{
    public string $organizationName = '';

    public string $slug = '';

    public bool $customSlug = false;

    /**
     * @var list<string>
     */
    public array $suggestedSlugs = [];

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Mount the component and verify registration permissions.
     */
    public function mount(): mixed
    {
        if (! Edition::canRegisterNewTenant()) {
            session()->flash('status', __('This self-hosted workspace is already initialized. Please sign in.'));

            return redirect()->route('login');
        }

        return null;
    }

    /**
     * Auto-generate subdomain slug in Cloud mode when organization name changes.
     */
    public function updatedOrganizationName(string $value): void
    {
        if (! $this->customSlug && Edition::isCloud()) {
            $this->slug = Str::slug($value);
            $this->checkSlugAvailability();
        }
    }

    /**
     * Track manual editing of the subdomain slug.
     */
    public function updatedSlug(): void
    {
        $this->customSlug = true;
        $this->checkSlugAvailability();
    }

    /**
     * Check if current slug is available or suggest alternatives.
     */
    public function checkSlugAvailability(): void
    {
        if (! Edition::isCloud() || empty($this->slug)) {
            $this->suggestedSlugs = [];

            return;
        }

        $cleanSlug = Str::slug($this->slug);
        $isTaken = Tenant::where('slug', $cleanSlug)->exists()
            || in_array($cleanSlug, Edition::RESERVED_SLUGS, true);

        if ($isTaken) {
            $this->suggestedSlugs = Edition::suggestAvailableSlugs($cleanSlug);
        } else {
            $this->suggestedSlugs = [];
        }
    }

    /**
     * Select a suggested slug.
     */
    public function selectSuggestedSlug(string $slug): void
    {
        $this->slug = $slug;
        $this->suggestedSlugs = [];
        $this->customSlug = true;
        $this->resetValidation('slug');
    }

    /**
     * Register a new workspace tenant, department, and admin user.
     */
    public function register(): mixed
    {
        if (! Edition::canRegisterNewTenant()) {
            session()->flash('status', __('This self-hosted workspace is already initialized. Please sign in.'));

            return redirect()->route('login');
        }

        $rules = [
            'organizationName' => 'required|string|min:2|max:100',
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ];

        if (Edition::isCloud()) {
            $cleanSlug = Str::slug($this->slug);
            $isTaken = Tenant::where('slug', $cleanSlug)->exists()
                || in_array($cleanSlug, Edition::RESERVED_SLUGS, true);

            if ($isTaken) {
                $this->suggestedSlugs = Edition::suggestAvailableSlugs($cleanSlug);
            }

            $rules['slug'] = [
                'required',
                'string',
                'alpha_dash',
                'min:2',
                'max:50',
                'unique:tenants,slug',
                Rule::notIn(Edition::RESERVED_SLUGS),
            ];
        }

        $this->validate($rules);

        if (Edition::isCloud()) {
            $finalSlug = strtolower(trim($this->slug));
        } else {
            $baseSlug = Str::slug($this->organizationName);
            if ($baseSlug === '') {
                $baseSlug = 'workspace';
            }

            $finalSlug = $baseSlug;
            $counter = 1;
            while (Tenant::where('slug', $finalSlug)->exists()) {
                $finalSlug = "{$baseSlug}-{$counter}";
                $counter++;
            }
        }

        // 1. Create Tenant Organization
        $tenant = Tenant::create([
            'name' => $this->organizationName,
            'slug' => $finalSlug,
            'settings' => [
                'theme' => 'dark',
                'timezone' => 'UTC',
            ],
        ]);

        // 2. Create Default Department
        $department = Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'General',
            'slug' => 'general',
            'color' => '#6366f1',
        ]);

        // 3. Create Tenant Admin User
        $user = User::create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'role' => 'admin',
            'current_capacity' => 0,
            'max_capacity' => 10,
            'total_points' => 0,
        ]);

        // In Cloud mode, redirect to the newly provisioned tenant subdomain via autologin signed URL
        if (Edition::isCloud()) {
            $signedUrl = Edition::signedTenantAutoLoginUrl($user, request());

            $this->redirect($signedUrl, navigate: false);

            return null;
        }

        Auth::login($user);
        session()->regenerate();

        return redirect()->route('board');
    }

    /**
     * Render the registration component view.
     */
    public function render(): View
    {
        return view('livewire.auth.register');
    }
}
