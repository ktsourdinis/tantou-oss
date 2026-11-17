<?php

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
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

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
     * Quick demo one-click login for local environment testing.
     */
    public function quickLogin(string $email): mixed
    {
        if (! app()->environment('local', 'testing')) {
            abort(403, __('Quick demo login is only available in local development.'));
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->addError('email', __('Demo user with email :email was not found. Please re-run seeders.', ['email' => $email]));

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
