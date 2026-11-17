<?php

namespace App\Livewire\Auth;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class ForgotPassword extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    public bool $linkSent = false;

    public ?string $devResetLink = null;

    /**
     * Send password reset link to user.
     */
    public function sendResetLink(): mixed
    {
        $this->validate();

        $cleanEmail = Str::lower(trim($this->email));
        $throttleKey = 'forgot-password|'.request()->ip().'|'.$cleanEmail;

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('email', __('Too many password reset requests. Please try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]));

            return null;
        }

        RateLimiter::hit($throttleKey, 300);

        // 1. Look up user (respecting current tenant if resolved, or across tenants if on central host)
        $userQuery = User::withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [$cleanEmail]);

        if (app()->bound('currentTenant')) {
            $userQuery->where('tenant_id', app('currentTenant')->id);
        }

        $user = $userQuery->first();

        // 2. Generate secure token and persist to password_reset_tokens
        if ($user) {
            $plainToken = Str::random(64);
            $hashedToken = Hash::make($plainToken);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $cleanEmail],
                [
                    'token' => $hashedToken,
                    'created_at' => now(),
                ]
            );

            // Construct Reset URL preserving workspace query parameter if applicable
            $queryParams = [
                'token' => $plainToken,
                'email' => $cleanEmail,
            ];

            if ($user->tenant && ! Edition::usesSubdomains(request())) {
                $queryParams['workspace'] = $user->tenant->slug;
            }

            if (Edition::usesSubdomains(request()) && $user->tenant) {
                $resetUrl = Edition::workspaceUrl(
                    $user->tenant,
                    route('password.reset', ['token' => $plainToken, 'email' => $cleanEmail], false),
                    request()
                );
            } else {
                $resetUrl = route('password.reset', $queryParams);
            }

            // Send password reset email
            try {
                Mail::to($user->email)->send(new ResetPasswordMail($plainToken, $user, $resetUrl));
            } catch (\Throwable $e) {
                Log::error('Failed to send password reset email: '.$e->getMessage());
            }

            Log::info("Tantou Password Reset Link for {$cleanEmail}: {$resetUrl}");

            // Local dev shortcut
            if (app()->environment('local', 'testing') || config('mail.default') === 'log') {
                $this->devResetLink = $resetUrl;
            }
        }

        $this->linkSent = true;

        return null;
    }

    /**
     * Reset form to allow entering a different email.
     */
    public function tryDifferentEmail(): void
    {
        $this->linkSent = false;
        $this->devResetLink = null;
        $this->email = '';
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password');
    }
}
