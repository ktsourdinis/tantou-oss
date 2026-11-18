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

namespace App\Livewire\Concerns;

use App\Livewire\Auth\Login;
use App\Models\Tenant;
use App\Services\StripeBillingService;
use App\Support\Edition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ManagesWorkspaceSettings
{
    public bool $showWorkspaceSettingsModal = false;

    public string $tenantSlug = '';

    public bool $strictClientIsolation = false;

    public bool $publicSubmissionEnabled = false;

    public string $tenantTimezone = 'Europe/Athens';

    public bool $telegramEnabled = false;

    public bool $telegramPrivacyMode = false;

    public string $telegramBotUsername = '';

    public string $telegramBotToken = '';

    public string $telegramWebhookSecret = '';

    public string $tenantCustomDomain = '';

    public string $selectedBillingInterval = 'monthly';

    /**
     * @var list<string>
     */
    public array $suggestedSlugs = [];

    /**
     * Open the Workspace Settings modal (Admin only).
     */
    public function openWorkspaceSettingsModal(): void
    {
        $currentUser = Auth::user();
        if (! $currentUser || ! $currentUser->isAdmin() || (int) $currentUser->tenant_id !== (int) $this->tenantId) {
            abort(403, 'Unauthorized.');
        }

        $tenant = Tenant::findOrFail($this->tenantId);
        $this->tenantName = $tenant->name;
        $this->tenantSlug = $tenant->slug;
        $this->tenantCustomDomain = (string) ($tenant->custom_domain ?? '');
        $this->strictClientIsolation = (bool) ($tenant->strict_client_isolation ?? false);
        $this->publicSubmissionEnabled = (bool) ($tenant->public_submission_enabled ?? false);
        $this->tenantTimezone = $tenant->timezone ?: 'Europe/Athens';
        $this->telegramEnabled = (bool) ($tenant->telegram_enabled ?? false);
        $this->telegramPrivacyMode = (bool) ($tenant->telegram_privacy_mode ?? false);
        $this->telegramBotUsername = (string) ($tenant->telegram_bot_username ?? '');
        $this->telegramBotToken = '';
        $this->telegramWebhookSecret = (string) ($tenant->telegram_webhook_secret ?? '');
        $this->suggestedSlugs = [];
        $this->showWorkspaceSettingsModal = true;
    }

    /**
     * Close the Workspace Settings modal.
     */
    public function closeWorkspaceSettingsModal(): void
    {
        $this->showWorkspaceSettingsModal = false;
        $this->suggestedSlugs = [];
        $this->resetValidation();
    }

    /**
     * Live validation and smart suggestions as the tenant slug is edited.
     */
    public function updatedTenantSlug(): void
    {
        $this->tenantSlug = Str::slug($this->tenantSlug);

        $isTaken = Tenant::where('slug', $this->tenantSlug)
            ->where('id', '!=', $this->tenantId)
            ->exists() || in_array($this->tenantSlug, Edition::RESERVED_SLUGS, true);

        if ($isTaken && ! empty($this->tenantSlug)) {
            $this->suggestedSlugs = Edition::suggestAvailableSlugs($this->tenantSlug, $this->tenantId);
        } else {
            $this->suggestedSlugs = [];
        }
    }

    /**
     * Generate smart slug suggestions on demand based on organization name or current slug.
     */
    public function suggestSlugsFromTenantName(): void
    {
        $base = ! empty($this->tenantName) ? $this->tenantName : $this->tenantSlug;
        $this->suggestedSlugs = Edition::suggestAvailableSlugs($base, $this->tenantId);
    }

    /**
     * Select one of the available suggested slugs.
     */
    public function selectSuggestedSlug(string $slug): void
    {
        $this->tenantSlug = $slug;
        $this->suggestedSlugs = [];
        $this->resetValidation('tenantSlug');
    }

    /**
     * Save workspace settings (organization name and subdomain slug).
     */
    public function saveWorkspaceSettings(): void
    {
        $currentUser = Auth::user();
        if (! $currentUser || ! $currentUser->isAdmin()) {
            abort(403, 'Unauthorized.');
        }

        $this->tenantSlug = Str::slug($this->tenantSlug);

        // Pre-check if slug is taken to populate suggestions when validation fails
        $isTaken = Tenant::where('slug', $this->tenantSlug)
            ->where('id', '!=', $this->tenantId)
            ->exists() || in_array($this->tenantSlug, Edition::RESERVED_SLUGS, true);

        if ($isTaken) {
            $this->suggestedSlugs = Edition::suggestAvailableSlugs($this->tenantSlug, $this->tenantId);
        }

        $this->validate([
            'tenantName' => ['required', 'string', 'min:2', 'max:100'],
            'tenantSlug' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'alpha_dash',
                Rule::notIn(Edition::RESERVED_SLUGS),
                Rule::unique('tenants', 'slug')->ignore($this->tenantId),
            ],
            'strictClientIsolation' => ['boolean'],
            'publicSubmissionEnabled' => ['boolean'],
            'tenantTimezone' => ['required', 'string', 'timezone:all'],
            'telegramEnabled' => ['boolean'],
            'telegramPrivacyMode' => ['boolean'],
            'telegramBotUsername' => ['nullable', 'string', 'max:100'],
            'telegramBotToken' => ['nullable', 'string'],
            'telegramWebhookSecret' => ['nullable', 'string', 'max:64'],
            'tenantCustomDomain' => [
                'nullable',
                'string',
                'min:3',
                'max:255',
                Rule::unique('tenants', 'custom_domain')->ignore($this->tenantId),
            ],
        ]);

        $currentUser = Auth::user();
        if (! $currentUser || ! $currentUser->isAdmin() || (int) $currentUser->tenant_id !== (int) $this->tenantId) {
            abort(403, 'Unauthorized.');
        }

        $tenant = Tenant::findOrFail($this->tenantId);
        $customDomainsEnabled = (bool) config('tantou-cloud.custom_domains_enabled', false);
        $isDemoSandbox = (bool) config('app.demo_mode', false)
            || $tenant->slug === 'hellenic-demo-ops'
            || Login::isDemoEmail((string) $currentUser->email);

        if (! $customDomainsEnabled) {
            $this->tenantCustomDomain = (string) ($tenant->custom_domain ?? '');
        } elseif ($isDemoSandbox && filled($this->tenantCustomDomain) && strtolower(trim($this->tenantCustomDomain)) !== strtolower((string) ($tenant->custom_domain ?? ''))) {
            $this->tenantCustomDomain = (string) ($tenant->custom_domain ?? '');
            $this->addError('tenantCustomDomain', __('🔒 Locked in Public Demo Sandbox'));

            return;
        } elseif (filled($this->tenantCustomDomain)) {
            $this->tenantCustomDomain = strtolower(trim($this->tenantCustomDomain));
            $this->tenantCustomDomain = (string) preg_replace('#^https?://#', '', $this->tenantCustomDomain);
            $this->tenantCustomDomain = rtrim(explode('/', $this->tenantCustomDomain)[0], '.');
            $this->tenantCustomDomain = explode(':', $this->tenantCustomDomain)[0];

            $hasCustomDomainPlan = $tenant->canUseFeature('custom_domain')
                && ! (Edition::isCloud() && $tenant->plan === 'community' && ! $tenant->isDemo());

            if (! $hasCustomDomainPlan) {
                $this->addError('tenantCustomDomain', __('Custom Domain requires the Cloud Business plan. 🔒 Upgrade to Tantou Cloud Managed to connect a custom CNAME domain'));

                return;
            }

            $appUrlHost = strtolower((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?? ''));
            $baseDomain = strtolower(Edition::baseDomain());
            $isValidHostname = preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $this->tenantCustomDomain) === 1;

            if (
                ! $isValidHostname
                || in_array($this->tenantCustomDomain, ['localhost', '127.0.0.1', $appUrlHost, $baseDomain], true)
            ) {
                $this->addError('tenantCustomDomain', __('Please enter a valid lowercase custom domain that does not match the primary platform host.'));

                return;
            }

            if (Tenant::where('custom_domain', $this->tenantCustomDomain)->where('id', '!=', $this->tenantId)->exists()) {
                $this->addError('tenantCustomDomain', __('This custom domain is already connected to another workspace.'));

                return;
            }
        }

        if ($this->strictClientIsolation && ! $tenant->canUseFeature('strict_b2b_isolation')) {
            $this->addError('strictClientIsolation', __('Strict B2B Client Isolation requires the Cloud Business plan.'));

            return;
        }

        if ($this->telegramPrivacyMode && ! $tenant->canUseFeature('gdpr_privacy_mode')) {
            $this->addError('telegramPrivacyMode', __('GDPR / Privacy Mode requires the Cloud Business plan.'));

            return;
        }

        if (filled($this->telegramBotToken) && ! $tenant->canUseFeature('custom_telegram_bot')) {
            $this->addError('telegramBotToken', __('Custom Dedicated Telegram Bot requires the Cloud Business plan.'));

            return;
        }

        $oldSlug = $tenant->slug;
        $isDemoMode = (bool) config('app.demo_mode', false);
        $newSlug = $isDemoMode ? $tenant->slug : $this->tenantSlug;
        $slugChanged = ($oldSlug !== $newSlug);

        $attemptedTelegramCredentialChange = $isDemoMode && (
            filled($this->telegramBotToken)
            || trim($this->telegramWebhookSecret) !== (string) ($tenant->telegram_webhook_secret ?? '')
            || ltrim(trim($this->telegramBotUsername), '@') !== (string) ($tenant->telegram_bot_username ?? '')
        );

        $updateData = [
            'name' => $this->tenantName,
            'slug' => $newSlug,
            'custom_domain' => ($isDemoMode || ! $customDomainsEnabled || $isDemoSandbox)
                ? $tenant->custom_domain
                : (filled($this->tenantCustomDomain) ? $this->tenantCustomDomain : null),
            'strict_client_isolation' => $this->strictClientIsolation,
            'public_submission_enabled' => $this->publicSubmissionEnabled,
            'timezone' => $this->tenantTimezone,
            'telegram_enabled' => $this->telegramEnabled,
            'telegram_privacy_mode' => $this->telegramPrivacyMode,
            'telegram_bot_username' => $isDemoMode
                ? $tenant->telegram_bot_username
                : (filled($this->telegramBotUsername) ? ltrim(trim($this->telegramBotUsername), '@') : null),
            'telegram_webhook_secret' => $isDemoMode
                ? $tenant->telegram_webhook_secret
                : (filled($this->telegramWebhookSecret) ? trim($this->telegramWebhookSecret) : null),
        ];

        if (! $isDemoMode && filled($this->telegramBotToken)) {
            $updateData['telegram_bot_token'] = trim($this->telegramBotToken);
        }

        $tenant->update($updateData);

        if ($attemptedTelegramCredentialChange) {
            $this->telegramBotToken = '';
            $this->telegramBotUsername = (string) ($tenant->telegram_bot_username ?? '');
            $this->telegramWebhookSecret = (string) ($tenant->telegram_webhook_secret ?? '');
            $this->addError('telegramBotToken', __('Modifying Telegram Bot tokens and webhook secrets is disabled in Demo Mode.'));

            return;
        }

        $this->tenantName = $tenant->name;
        $this->tenantSlug = $tenant->slug;
        $this->showWorkspaceSettingsModal = false;
        $this->suggestedSlugs = [];

        session()->flash('status', __('Workspace settings updated successfully.'));

        if ($slugChanged) {
            if (Edition::usesSubdomains(request())) {
                $currentUser->load('tenant');
                $signedUrl = Edition::signedTenantAutoLoginUrl($currentUser, request());
                $this->redirect($signedUrl, navigate: false);

                return;
            }

            session(['active_tenant_id' => $tenant->id]);
            $this->redirect(Edition::workspaceUrl($tenant, '/board', request()), navigate: false);

            return;
        }
    }

    /**
     * Get the shareable public guest request drop-box URL for this workspace.
     */
    public function getPublicDropBoxUrlProperty(): string
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return '';
        }

        if (Edition::usesSubdomains(request())) {
            return Edition::workspaceUrl($tenant, '/submit', request());
        }

        $request = request();
        $scheme = $request ? $request->getScheme() : 'http';
        $port = $request ? $request->getPort() : 80;
        $portString = ($port && ! in_array((int) $port, [80, 443], true)) ? ":{$port}" : '';
        $host = $request ? $request->getHost() : (parse_url((string) config('app.url'), PHP_URL_HOST) ?? 'localhost');

        return "{$scheme}://{$host}{$portString}/submit/{$tenant->slug}";
    }

    /**
     * Start Stripe checkout flow for subscription upgrade or renewal.
     */
    public function startStripeCheckout(string $plan, string $interval = 'monthly'): mixed
    {
        if (! Edition::isCloud()) {
            abort(403, 'Stripe billing is only available in Cloud edition.');
        }

        $currentUser = Auth::user();
        if (! $currentUser || ! $currentUser->isAdmin()) {
            abort(403);
        }

        $tenant = Tenant::findOrFail($this->tenantId);
        $service = app(StripeBillingService::class);
        $checkoutUrl = $service->createCheckoutSession($tenant, $plan, $interval);

        return redirect()->to($checkoutUrl);
    }

    /**
     * Open Stripe customer billing portal for card/invoice management.
     */
    public function openBillingPortal(): mixed
    {
        if (! Edition::isCloud()) {
            abort(403, 'Stripe billing is only available in Cloud edition.');
        }

        $currentUser = Auth::user();
        if (! $currentUser || ! $currentUser->isAdmin()) {
            abort(403);
        }

        $tenant = Tenant::findOrFail($this->tenantId);
        $service = app(StripeBillingService::class);
        $portalUrl = $service->createBillingPortalSession($tenant);

        return redirect()->to($portalUrl);
    }
}
