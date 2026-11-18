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
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ResetPassword extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Mount component with token and optional email from query string.
     */
    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    /**
     * Reset password and log in.
     */
    public function resetPassword(): mixed
    {
        $this->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'string',
                'min:10',
                'confirmed',
                Password::min(10)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $cleanEmail = Str::lower(trim($this->email));

        // 1. Validate token record from password_reset_tokens
        $record = DB::table('password_reset_tokens')->where('email', $cleanEmail)->first();

        if (! $record || ! Hash::check($this->token, $record->token)) {
            $this->addError('email', __('This password reset token is invalid or has already been used.'));

            return null;
        }

        // 2. Check 60-minute expiration window
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $cleanEmail)->delete();
            $this->addError('email', __('This password reset link has expired. Please request a new one.'));

            return null;
        }

        // 3. Find matching user
        $user = User::withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [$cleanEmail])->first();

        if (! $user) {
            $this->addError('email', __('No user account was found with this email address.'));

            return null;
        }

        // 4. Update user password
        $user->forceFill([
            'password' => Hash::make($this->password),
        ])->save();

        // 5. Delete used token
        DB::table('password_reset_tokens')->where('email', $cleanEmail)->delete();

        // 6. Clear any auth rate limiting / lockout
        RateLimiter::clear(Str::lower($cleanEmail).'|'.request()->ip());
        RateLimiter::clear('forgot-password|'.request()->ip().'|'.$cleanEmail);

        // 7. Authenticate user and redirect to workspace
        Auth::login($user);
        session()->regenerate();
        session()->flash('status', __('Your password has been successfully reset! Welcome back.'));

        if ($user->tenant) {
            session(['active_tenant_id' => $user->tenant->id]);
            $redirectUrl = Edition::workspaceUrl($user->tenant, '/board', request());

            $this->redirect($redirectUrl, navigate: false);

            return null;
        }

        $this->redirectIntended('/board', navigate: false);

        return null;
    }

    public function render(): View
    {
        return view('livewire.auth.reset-password');
    }
}
