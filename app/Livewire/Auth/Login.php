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

use App\Models\User;
use App\Support\Edition;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class Login extends Component
{
    /**
     * Explicit list of allowed seeded demo account emails.
     *
     * @var list<string>
     */
    public const array ALLOWED_DEMO_EMAILS = [
        'admin@tantou.local',
        'coordinator@tantou.local',
        'elena@tantou.local',
        'kostas@tantou.local',
        'maria@tantou.local',
        'giorgos@tantou.local',
        'client@tantou.local',
        'lab@tantou.local',
    ];

    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Determine whether 1-click quick demo login is enabled.
     */
    public static function isDemoLoginEnabled(): bool
    {
        return (bool) config('app.demo_mode', false) || app()->environment('local', 'testing');
    }

    /**
     * Determine whether the provided identifier is an explicit @tantou.local demo email.
     */
    public static function isDemoEmail(string $email): bool
    {
        $normalized = strtolower(trim($email));

        if ($normalized === '' || is_numeric($normalized) || ! str_contains($normalized, '@')) {
            return false;
        }

        return in_array($normalized, self::ALLOWED_DEMO_EMAILS, true)
            || str_ends_with($normalized, '@tantou.local');
    }

    /**
     * Authenticate the user with provided credentials.
     */
    public function authenticate(): mixed
    {
        $this->validate();

        $throttleKey = Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('email', __('Too many login attempts. Please try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]));
            $this->password = '';

            return null;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);

            $this->addError('email', __('The provided credentials do not match our records.'));
            $this->password = '';

            return null;
        }

        // In Cloud mode on a subdomain, restrict login strictly to users of this tenant
        if (Edition::usesSubdomains(request()) && app()->bound('currentTenant')) {
            $currentTenant = app('currentTenant');
            $user = Auth::user();

            if ($user && (int) $user->tenant_id !== (int) $currentTenant->id) {
                RateLimiter::hit($throttleKey, 60);

                Auth::logout();
                session()->invalidate();
                session()->regenerateToken();

                $this->addError('email', __('You do not have access to this workspace.'));
                $this->password = '';

                return null;
            }
        }

        RateLimiter::clear($throttleKey);

        session()->regenerate();

        $user = Auth::user();

        // In Subdomain mode on the central domain, redirect to tenant subdomain with signed auto-login
        if (Edition::usesSubdomains(request()) && $user && $user->tenant) {
            $host = request()->getHost();
            $baseDomain = Edition::baseDomain();

            if ($host === $baseDomain) {
                $signedSubdomainUrl = Edition::signedTenantAutoLoginUrl($user, request());

                $this->redirect($signedSubdomainUrl, navigate: false);

                return null;
            }
        } elseif (! Edition::usesSubdomains(request()) && $user && $user->tenant) {
            session(['active_tenant_id' => $user->tenant->id]);
            $redirectUrl = Edition::workspaceUrl($user->tenant, '/board', request());

            $this->redirect($redirectUrl, navigate: false);

            return null;
        }

        $this->redirectIntended('/board', navigate: false);

        return null;
    }

    /**
     * Quick demo one-click login for demo mode or local environment testing.
     */
    public function quickLogin(string $email): mixed
    {
        if (! self::isDemoLoginEnabled()) {
            abort(403, __('Quick demo login is only available in Demo Mode or local development.'));
        }

        $cleanEmail = strtolower(trim($email));

        if (! self::isDemoEmail($cleanEmail)) {
            abort(403, __('Only explicit demo accounts (@tantou.local) are permitted for quick demo login.'));
        }

        $user = User::where('email', $cleanEmail)->first();

        if ($user === null) {
            $this->addError('email', __('Demo user with email :email was not found. Please re-run seeders.', ['email' => $cleanEmail]));

            return null;
        }

        if (Edition::usesSubdomains(request()) && app()->bound('currentTenant')) {
            $currentTenant = app('currentTenant');
            if ((int) $user->tenant_id !== (int) $currentTenant->id) {
                $this->addError('email', __('You do not have access to this workspace.'));

                return null;
            }
        }

        Auth::login($user, true);
        session()->regenerate();

        if (Edition::usesSubdomains(request()) && $user->tenant) {
            $host = request()->getHost();
            $baseDomain = Edition::baseDomain();

            if ($host === $baseDomain) {
                $signedSubdomainUrl = Edition::signedTenantAutoLoginUrl($user, request());

                $this->redirect($signedSubdomainUrl, navigate: false);

                return null;
            }
        } elseif (! Edition::usesSubdomains(request()) && $user->tenant) {
            session(['active_tenant_id' => $user->tenant->id]);
            $redirectUrl = Edition::workspaceUrl($user->tenant, '/board', request());

            $this->redirect($redirectUrl, navigate: false);

            return null;
        }

        $this->redirectIntended('/board', navigate: false);

        return null;
    }

    /**
     * Render the login component view.
     */
    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
