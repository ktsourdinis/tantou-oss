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

namespace App\Livewire;

use App\Events\BoardUpdated;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\PostItTransfer;
use App\Models\Tenant;
use App\Services\BountyCalculator;
use App\Services\TelegramBotService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class PublicSubmitRequest extends Component
{
    use WithFileUploads;

    public ?Tenant $tenant = null;

    #[Locked]
    public ?int $tenantId = null;

    #[Validate('required|string|min:2|max:100')]
    public string $guest_name = '';

    #[Validate('required|string|min:2|max:100')]
    public string $facility_name = '';

    #[Validate('nullable|string|max:100')]
    public string $guest_contact = '';

    public ?int $department_id = null;

    #[Validate('required|string|min:3|max:160')]
    public string $title = '';

    #[Validate('nullable|string|max:2000')]
    public string $description = '';

    /**
     * @var array<TemporaryUploadedFile>
     */
    public array $attachments = [];

    /**
     * Honeypot field (bots fill this, humans leave empty).
     */
    public string $website_url = '';

    public bool $isSubmitting = false;

    /**
     * Mount and resolve tenant & prefill query parameters.
     */
    public function mount(?string $tenantSlug = null): void
    {
        $tenant = null;
        $workspaceQuery = request()->query('workspace');
        $resolvedSlug = ! empty($tenantSlug)
            ? $tenantSlug
            : (is_string($workspaceQuery) && filled($workspaceQuery) ? $workspaceQuery : null);

        if (! empty($resolvedSlug)) {
            $tenant = Tenant::where('slug', strtolower(trim($resolvedSlug)))->first();
            if (! $tenant) {
                abort(404, __('Workspace not found.'));
            }
        } elseif (app()->bound('currentTenant')) {
            $tenant = app('currentTenant');
        } else {
            $tenant = Tenant::where('slug', 'hellenic-demo-ops')->first() ?? Tenant::first();
        }

        if (! $tenant || ! $tenant->public_submission_enabled) {
            abort(403, __('Public request submission is disabled for this workspace.'));
        }

        app()->instance('currentTenant', $tenant);
        $this->tenant = $tenant;
        $this->tenantId = $tenant->id;

        // Auto-select first department as default
        $departments = Department::where('tenant_id', $this->tenantId)->get();
        if ($departments->isNotEmpty()) {
            $this->department_id = $departments->first()->id;
        }

        // Pre-fill facility if passed via query string (?facility=...) and auto-select matching department
        $queryFacility = request()->query('facility');
        if (is_string($queryFacility) && filled($queryFacility)) {
            $trimmedFacility = trim($queryFacility);
            $this->facility_name = $trimmedFacility;

            $matchingDepartment = $departments->first(function (Department $dept) use ($trimmedFacility): bool {
                return mb_strtolower($dept->name) === mb_strtolower($trimmedFacility)
                    || mb_strtolower($dept->slug) === mb_strtolower($trimmedFacility);
            });

            if ($matchingDepartment) {
                $this->department_id = $matchingDepartment->id;
            }
        }
    }

    /**
     * Ensure currentTenant instance is bound during Livewire hydration.
     */
    public function hydrate(): void
    {
        if ($this->tenantId && ! $this->tenant) {
            $this->tenant = Tenant::find($this->tenantId);
        }

        if ($this->tenant) {
            app()->instance('currentTenant', $this->tenant);
        }
    }

    /**
     * Submit the public request to the backlog.
     */
    public function submit(): mixed
    {
        // 1. Honeypot check
        if (! empty($this->website_url)) {
            $this->addError('title', __('Spam submission detected.'));

            return null;
        }

        // 2. Strict Rate Limiting: Max 5 submissions per 10 minutes per IP
        $throttleKey = 'public-submit|'.request()->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('title', __('Too many requests submitted. Please try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]));

            return null;
        }

        $this->validate([
            'guest_name' => ['required', 'string', 'min:2', 'max:100'],
            'facility_name' => ['required', 'string', 'min:2', 'max:100'],
            'guest_contact' => ['nullable', 'string', 'max:100'],
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where('tenant_id', $this->tenantId),
            ],
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:2'],
            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:pdf,png,jpg,jpeg,webp,txt,csv,doc,docx,xls,xlsx,zip',
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (in_array($ext, ['php', 'phtml', 'sh', 'js', 'html', 'htm', 'svg'], true)) {
                        $fail(__('Executable and script files are not allowed.'));
                    }
                },
            ],
        ]);

        RateLimiter::hit($throttleKey, 600);

        $this->isSubmitting = true;

        $calculator = app(BountyCalculator::class);
        $bountyPoints = $calculator->calculate(
            weight: 1,
            priority: 'normal',
            tenantId: $this->tenantId,
        );

        $postIt = PostIt::create([
            'tenant_id' => $this->tenantId,
            'department_id' => $this->department_id,
            'requester_id' => null,
            'guest_name' => trim($this->guest_name),
            'facility_name' => trim($this->facility_name),
            'guest_contact' => filled($this->guest_contact) ? trim($this->guest_contact) : null,
            'assignee_id' => null,
            'assigned_by_id' => null,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => trim($this->title),
            'description' => filled($this->description) ? trim($this->description) : null,
            'status' => 'backlog',
            'priority' => 'normal',
            'weight' => 1,
            'bounty_points' => $bountyPoints,
            'started_at' => null,
            'completed_at' => null,
        ]);

        // Store attachments using secure randomized UUID filenames
        if (! empty($this->attachments)) {
            foreach ($this->attachments as $file) {
                $ext = strtolower($file->getClientOriginalExtension()) ?: 'bin';
                $uuidName = Str::uuid().'.'.$ext;
                $storedPath = $file->storeAs("tenants/{$this->tenantId}/post-its/{$postIt->id}", $uuidName, 'local');
                $postIt->attachments()->create([
                    'uploader_id' => null,
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $storedPath,
                    'mime_type' => $file->getClientMimeType() ?: ($file->getMimeType() ?: 'application/octet-stream'),
                    'file_size' => $file->getSize() ?: 0,
                ]);
            }
        }

        // Record initial creation trail
        PostItTransfer::create([
            'post_it_id' => $postIt->id,
            'actor_id' => null,
            'from_user_id' => null,
            'to_user_id' => null,
            'action' => 'created',
            'created_at' => now(),
        ]);

        // Log entry as initial system comment
        $postIt->comments()->create([
            'user_id' => null,
            'guest_name' => __('System'),
            'body' => __('Submitted via Public Drop-Box by :guest (:facility)', [
                'guest' => $this->guest_name,
                'facility' => $this->facility_name,
            ]),
        ]);

        // Real-time broadcast so staff board refreshes immediately
        app(TelegramBotService::class)->routePostItNotification($postIt->fresh());
        BoardUpdated::dispatch($this->tenantId);

        return redirect()->route('public.track', ['token' => $postIt->tracking_token]);
    }

    /**
     * Remove an attachment before submitting.
     */
    public function removeAttachment(int $index): void
    {
        if (isset($this->attachments[$index])) {
            unset($this->attachments[$index]);
            $this->attachments = array_values($this->attachments);
        }
    }

    /**
     * Render the component view.
     */
    public function render(): View
    {
        $departments = Department::where('tenant_id', $this->tenantId)
            ->orderBy('name')
            ->get();

        return view('livewire.public-submit-request', [
            'departments' => $departments,
        ]);
    }
}
