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

use App\Mail\VerifyRegistrationMagicLinkMail;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class RegisterTenant extends Component
{
    #[Validate('required|email|max:255|unique:users,email')]
    public string $email = '';

    /**
     * Invisible honeypot field (bots fill this, humans leave it empty).
     */
    public string $website_url = '';

    public bool $linkSent = false;

    public ?string $devMagicLink = null;

    public string $plan = 'team';

    /**
     * Mount and prune expired records.
     */
    public function mount(): ?RedirectResponse
    {
        // 1. Check self-hosted single tenant lock
        if (! Edition::canRegisterNewTenant()) {
            session()->flash('status', __('This self-hosted workspace is already initialized. Please sign in.'));

            return redirect()->route('login');
        }

        $queryPlan = (string) request()->query('plan');
        if (in_array($queryPlan, ['team', 'business'], true)) {
            $this->plan = $queryPlan;
        }

        // 2. Automatically delete expired pending registration attempts
        PendingRegistration::where('expires_at', '<=', now())->delete();

        return null;
    }

    /**
     * Request a registration magic link.
     */
    public function requestMagicLink(): mixed
    {
        if (! Edition::canRegisterNewTenant()) {
            session()->flash('status', __('This self-hosted workspace is already initialized. Please sign in.'));

            return redirect()->route('login');
        }

        // 1. Honeypot check
        if (! empty($this->website_url)) {
            // Silently pretend success to mislead spambots
            $this->linkSent = true;

            return null;
        }

        $this->validate();

        // 2. Strict Rate Limiting: Max 3 requests per 10 minutes per IP & email
        $cleanEmail = Str::lower(trim($this->email));
        $throttleKey = 'magic-reg|'.request()->ip().'|'.$cleanEmail;

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('email', __('Too many registration link requests. Please try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]));

            return null;
        }

        RateLimiter::hit($throttleKey, 600);

        // 3. Auto-prune expired records
        PendingRegistration::where('expires_at', '<=', now())->delete();

        // 4. Generate fresh 64-char plain token and store hash
        $plainToken = Str::random(64);
        $tokenHash = hash('sha256', $plainToken);

        PendingRegistration::updateOrCreate(
            ['email' => $cleanEmail],
            [
                'token_hash' => $tokenHash,
                'ip_address' => request()->ip(),
                'expires_at' => now()->addMinutes(30),
            ]
        );

        $magicUrl = route('register.complete', ['token' => $plainToken, 'plan' => $this->plan]);

        // 5. Send Magic Link Email
        try {
            Mail::to($cleanEmail)->send(new VerifyRegistrationMagicLinkMail($plainToken, $cleanEmail, $magicUrl));
        } catch (\Throwable $e) {
            Log::error('Failed to send registration magic link email: '.$e->getMessage());
        }

        // In local/testing mode or log driver, log the link and make dev URL available
        Log::info("Tantou Registration Magic Link for {$cleanEmail}: {$magicUrl}");

        if (app()->environment('local', 'testing') || config('mail.default') === 'log') {
            $this->devMagicLink = $magicUrl;
        }

        $this->linkSent = true;

        return null;
    }

    /**
     * Allow user to enter a different email.
     */
    public function changeEmail(): void
    {
        $this->linkSent = false;
        $this->devMagicLink = null;
        $this->email = '';
    }

    /**
     * Render the component view.
     */
    public function render(): View
    {
        return view('livewire.auth.register-tenant');
    }
}
