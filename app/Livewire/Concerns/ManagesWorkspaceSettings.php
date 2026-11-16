<?php

namespace App\Livewire\Concerns;

use App\Models\Tenant;
use App\Support\Edition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ManagesWorkspaceSettings
{
    public bool $showWorkspaceSettingsModal = false;

    public string $tenantSlug = '';

    public bool $strictClientIsolation = false;

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
        if (! $currentUser || ! $currentUser->isAdmin()) {
            abort(403, 'Unauthorized.');
        }

        $tenant = Tenant::findOrFail($this->tenantId);
        $this->tenantName = $tenant->name;
        $this->tenantSlug = $tenant->slug;
        $this->strictClientIsolation = (bool) ($tenant->strict_client_isolation ?? false);
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
        ]);

        $tenant = Tenant::findOrFail($this->tenantId);
        $oldSlug = $tenant->slug;
        $newSlug = $this->tenantSlug;
        $slugChanged = ($oldSlug !== $newSlug);

        $tenant->update([
            'name' => $this->tenantName,
            'slug' => $newSlug,
            'strict_client_isolation' => $this->strictClientIsolation,
        ]);

        $this->tenantName = $tenant->name;
        $this->tenantSlug = $tenant->slug;
        $this->showWorkspaceSettingsModal = false;
        $this->suggestedSlugs = [];

        session()->flash('status', __('Workspace settings updated successfully.'));

        if ($slugChanged && Edition::isCloud()) {
            $currentUser->load('tenant');
            $signedUrl = Edition::signedTenantAutoLoginUrl($currentUser, request());
            $this->redirect($signedUrl, navigate: false);

            return;
        }
    }
}
