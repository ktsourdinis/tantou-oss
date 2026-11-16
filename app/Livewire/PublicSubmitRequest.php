<?php

namespace App\Livewire;

use App\Events\BoardUpdated;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\PostItTransfer;
use App\Models\Tenant;
use App\Services\BountyCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class PublicSubmitRequest extends Component
{
    use WithFileUploads;

    public ?Tenant $tenant = null;

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

    public bool $isSubmitting = false;

    /**
     * Mount and resolve tenant & prefill query parameters.
     */
    public function mount(?string $tenantSlug = null): void
    {
        $tenant = null;

        if (app()->bound('currentTenant')) {
            $tenant = app('currentTenant');
        } elseif (! empty($tenantSlug)) {
            $tenant = Tenant::where('slug', $tenantSlug)->first();
        } else {
            $tenant = Tenant::first();
        }

        if (! $tenant || ! $tenant->public_submission_enabled) {
            abort(403, __('Public request submission is disabled for this workspace.'));
        }

        $this->tenant = $tenant;
        $this->tenantId = $tenant->id;

        // Auto-select first department if only one exists
        $firstDepartment = Department::where('tenant_id', $this->tenantId)->first();
        if ($firstDepartment) {
            $this->department_id = $firstDepartment->id;
        }

        // Pre-fill facility if passed via query string (?facility=...)
        $queryFacility = request()->query('facility');
        if (is_string($queryFacility) && filled($queryFacility)) {
            $this->facility_name = trim($queryFacility);
        }
    }

    /**
     * Submit the public request to the backlog.
     */
    public function submit(): mixed
    {
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
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,csv,txt,zip'],
        ]);

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

        // Store attachments if uploaded
        if (! empty($this->attachments)) {
            foreach ($this->attachments as $file) {
                $storedPath = $file->store("tenants/{$this->tenantId}/post-its/{$postIt->id}", 'local');
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
