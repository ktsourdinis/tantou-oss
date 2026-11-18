<?php

/**
 * Tantou (担当) — Human Capacity OS
 *
 * @author    Konstantinos Tsourdinis / HELLENIC WEB3 STUDIO
 * @copyright 2026 Konstantinos Tsourdinis / HELLENIC WEB3 STUDIO
 * @license   AGPL-3.0-or-later OR LicenseRef-Tantou-Commercial
 *
 * @link      https://github.com/ktsourdinis/tantou-oss
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later OR LicenseRef-Tantou-Commercial
 */

namespace App\Livewire\Auth;

use App\Models\Department;
use App\Models\PendingRegistration;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeBillingService;
use App\Support\Edition;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CompleteRegistration extends Component
{
    public string $token = '';

    public string $email = '';

    public bool $isExpiredOrInvalid = false;

    public string $organization_name = '';

    public string $name = '';

    public string $slug = '';

    public bool $customSlug = false;

    /**
     * @var list<string>
     */
    public array $suggestedSlugs = [];

    public string $password = '';

    public string $password_confirmation = '';

    public string $plan = 'cloud_managed';

    public string $billing_interval = 'monthly';

    /**
     * Mount and validate token against pending registrations.
     */
    public function mount(string $token): ?RedirectResponse
    {
        // Check self-hosted single tenant lock
        if (! Edition::canRegisterNewTenant()) {
            session()->flash('status', __('This self-hosted workspace is already initialized. Please sign in.'));

            return redirect()->route('login');
        }

        $this->token = trim($token);

        // 1. Auto-prune expired records
        PendingRegistration::where('expires_at', '<=', now())->delete();

        // 2. Validate token hash
        $tokenHash = hash('sha256', $this->token);
        $pending = PendingRegistration::where('token_hash', $tokenHash)
            ->where('expires_at', '>', now())
            ->first();

        if ($pending === null) {
            $this->isExpiredOrInvalid = true;

            return null;
        }

        $this->email = $pending->email;

        if (filled($pending->proposed_org_name)) {
            $this->organization_name = $pending->proposed_org_name;
        }

        if (filled($pending->proposed_slug)) {
            $this->slug = $pending->proposed_slug;
            $this->customSlug = true;
        }

        $queryPlan = (string) request()->query('plan');
        if (in_array($queryPlan, ['cloud_managed', 'cloud', 'team'], true)) {
            $this->plan = 'cloud_managed';
        }

        $queryInterval = (string) request()->query('interval');
        if (in_array($queryInterval, ['monthly', 'yearly'], true)) {
            $this->billing_interval = $queryInterval;
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
     * Complete the registration: provision organization, admin, and log in.
     */
    public function complete(): mixed
    {
        if (! Edition::canRegisterNewTenant()) {
            session()->flash('status', __('This self-hosted workspace is already initialized. Please sign in.'));

            return redirect()->route('login');
        }

        // 1. Auto-prune and re-verify token
        PendingRegistration::where('expires_at', '<=', now())->delete();

        $tokenHash = hash('sha256', $this->token);
        $pending = PendingRegistration::where('token_hash', $tokenHash)
            ->where('expires_at', '>', now())
            ->first();

        if ($pending === null) {
            $this->isExpiredOrInvalid = true;

            return null;
        }

        // 2. Validate inputs
        $rules = [
            'organization_name' => ['required', 'string', 'min:2', 'max:100'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'plan' => ['required', 'in:cloud_managed,cloud,team'],
            'billing_interval' => ['required', 'in:monthly,yearly'],
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

        // 3. Resolve final slug
        if (Edition::isCloud()) {
            $finalSlug = strtolower(trim($this->slug));
        } else {
            $baseSlug = Str::slug($this->organization_name);
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

        // 4. Provision in DB Transaction
        $interval = in_array($this->billing_interval, ['monthly', 'yearly'], true) ? $this->billing_interval : 'monthly';

        $user = DB::transaction(function () use ($pending, $finalSlug, $interval) {
            // A. Create Tenant under Tantou Cloud Managed plan with 14-day trial
            $tenant = Tenant::create([
                'name' => trim($this->organization_name),
                'slug' => $finalSlug,
                'plan' => 'cloud_managed',
                'billing_interval' => $interval,
                'subscription_status' => 'trialing',
                'trial_ends_at' => now()->addDays(14),
                'settings' => [
                    'theme' => 'dark',
                    'timezone' => 'UTC',
                ],
            ]);

            // B. Create Default Department
            $department = Department::create([
                'tenant_id' => $tenant->id,
                'name' => 'General',
                'slug' => 'general',
                'color' => '#6366f1',
            ]);

            // C. Create Verified Admin User
            $admin = User::create([
                'tenant_id' => $tenant->id,
                'department_id' => $department->id,
                'name' => trim($this->name),
                'email' => $pending->email,
                'email_verified_at' => now(),
                'password' => Hash::make($this->password),
                'role' => 'admin',
                'current_capacity' => 0,
                'max_capacity' => 10,
                'total_points' => 0,
            ]);

            // D. Immediately delete the temporary pending registration
            $pending->delete();

            return $admin;
        });

        // 5. Authenticate and redirect to Stripe Checkout (if configured) or 14-day trial board on current host
        Auth::login($user);
        session()->regenerate();

        $tenant = $user->tenant;

        if ($tenant) {
            session(['active_tenant_id' => $tenant->id]);
            app()->instance('currentTenant', $tenant);
        }

        if (Edition::isCloud() && $tenant) {
            $stripeSecret = (string) config('tantou.stripe.secret');
            $stripePriceId = (string) (
                config("tantou.stripe.prices.cloud_managed.{$interval}")
                ?: config("tantou.stripe.prices.cloud.{$interval}")
            );

            if (filled($stripeSecret) && filled($stripePriceId)) {
                $checkoutUrl = app(StripeBillingService::class)->createCheckoutSession($tenant, 'cloud_managed', $interval);

                return redirect()->away($checkoutUrl);
            }

            return redirect()->to(route('board', ['workspace' => $tenant->slug]));
        }

        return redirect()->to('/board');
    }

    /**
     * Render the component view.
     */
    public function render(): View
    {
        return view('livewire.auth.complete-registration');
    }
}
