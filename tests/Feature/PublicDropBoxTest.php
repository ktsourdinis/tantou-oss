<?php

namespace Tests\Feature;

use App\Events\BoardUpdated;
use App\Livewire\CapacityBoard;
use App\Livewire\PublicSubmitRequest;
use App\Livewire\PublicTrackRequest;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PublicDropBoxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Edition::fake(false);
    }

    protected function tearDown(): void
    {
        Edition::fake(null);
        parent::tearDown();
    }

    public function test_visiting_submit_returns_403_when_public_submission_is_disabled(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'acme-disabled',
            'public_submission_enabled' => false,
        ]);

        $response = $this->get('/submit/'.$tenant->slug);
        $response->assertStatus(403);
    }

    public function test_guest_can_submit_request_and_is_redirected_to_live_tracker(): void
    {
        Storage::fake('local');
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create([
            'slug' => 'hospital-ops',
            'public_submission_enabled' => true,
        ]);

        $department = Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'IT Support',
            'slug' => 'it-support',
            'color' => '#6366f1',
        ]);

        $component = Livewire::withQueryParams(['facility' => 'Ακτινολογικό'])
            ->test(PublicSubmitRequest::class, ['tenantSlug' => $tenant->slug])
            ->assertSet('facility_name', 'Ακτινολογικό')
            ->assertSet('department_id', $department->id)
            ->set('guest_name', 'Maria Papadopoulou')
            ->set('guest_contact', 'Εσωτ. 2140')
            ->set('title', 'PACS Workstation network cable damaged')
            ->set('description', 'Screen shows network unplugged error in room 12.')
            ->call('submit');

        $postIt = PostIt::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($postIt);
        $this->assertSame('Maria Papadopoulou', $postIt->guest_name);
        $this->assertSame('Ακτινολογικό', $postIt->facility_name);
        $this->assertSame('Εσωτ. 2140', $postIt->guest_contact);
        $this->assertNull($postIt->requester_id);
        $this->assertSame('backlog', $postIt->status);
        $this->assertSame('PACS Workstation network cable damaged', $postIt->title);
        $this->assertNotNull($postIt->tracking_token);
        $this->assertStringStartsWith('TNT-', $postIt->tracking_token);

        $component->assertRedirect(route('public.track', ['token' => $postIt->tracking_token]));

        // Initial workflow comments and transfers recorded
        $this->assertDatabaseHas('post_it_transfers', [
            'post_it_id' => $postIt->id,
            'action' => 'created',
            'actor_id' => null,
        ]);

        $this->assertDatabaseHas('post_it_comments', [
            'post_it_id' => $postIt->id,
            'user_id' => null,
        ]);

        Event::assertDispatched(BoardUpdated::class);
    }

    public function test_guest_can_upload_attachments_during_public_submission(): void
    {
        Storage::fake('local');

        $tenant = Tenant::factory()->create([
            'slug' => 'city-clinic',
            'public_submission_enabled' => true,
        ]);

        $department = Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'Biomedical Service',
            'slug' => 'biomed',
            'color' => '#10b981',
        ]);

        $file = UploadedFile::fake()->create('error_log.pdf', 500, 'application/pdf');

        Livewire::test(PublicSubmitRequest::class, ['tenantSlug' => $tenant->slug])
            ->set('guest_name', 'Giannis Ant')
            ->set('facility_name', 'ICU')
            ->set('department_id', $department->id)
            ->set('title', 'Ventilator sensor calibration needed')
            ->set('attachments', [$file])
            ->call('submit');

        $postIt = PostIt::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($postIt);
        $this->assertCount(1, $postIt->attachments);
        $this->assertSame('error_log.pdf', $postIt->attachments->first()->original_name);
        $this->assertNull($postIt->attachments->first()->uploader_id);
    }

    public function test_visiting_track_renders_status_queue_position_and_allows_guest_comment(): void
    {
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create(['public_submission_enabled' => true]);

        $worker = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Nikos Tech',
            'role' => 'worker',
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        // Create earlier post-it for same worker so queue position is #2
        PostIt::create([
            'tenant_id' => $tenant->id,
            'title' => 'Urgent Server Patch',
            'status' => 'doing',
            'priority' => 'urgent',
            'weight' => 2,
            'assignee_id' => $worker->id,
            'created_at' => now()->subHours(2),
        ]);

        // Target post-it assigned to worker
        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'title' => 'Guest Request for Printer Repair',
            'guest_name' => 'Helen Smith',
            'guest_contact' => 'Ext. 402',
            'facility_name' => 'Radiology',
            'status' => 'assigned',
            'priority' => 'normal',
            'weight' => 1,
            'assignee_id' => $worker->id,
            'tracking_token' => 'TNT-TEST9999',
            'created_at' => now()->subHour(),
        ]);

        // Test Livewire tracker component
        Livewire::test(PublicTrackRequest::class, ['token' => 'TNT-TEST9999'])
            ->assertSee('Guest Request for Printer Repair')
            ->assertSee('Radiology')
            ->assertSee('#TNT-TEST9999')
            ->assertSee('Nikos Tech')
            ->assertSee('Queue Position: #2 in active queue')
            ->set('newCommentBody', 'Technician, please call room 4 before coming up.')
            ->call('addComment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('post_it_comments', [
            'post_it_id' => $postIt->id,
            'guest_name' => 'Helen Smith (Guest)',
            'body' => 'Technician, please call room 4 before coming up.',
        ]);

        Event::assertDispatched(BoardUpdated::class);
    }

    public function test_guest_cannot_comment_on_completed_request(): void
    {
        $tenant = Tenant::factory()->create(['public_submission_enabled' => true]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'title' => 'Finished Task',
            'guest_name' => 'Guest User',
            'status' => 'done',
            'tracking_token' => 'TNT-DONE0001',
            'completed_at' => now(),
        ]);

        Livewire::test(PublicTrackRequest::class, ['token' => 'TNT-DONE0001'])
            ->assertSee('Request Completed!')
            ->set('newCommentBody', 'Can I still reopen this?')
            ->call('addComment')
            ->assertHasErrors(['newCommentBody']);
    }

    public function test_capacity_board_renders_guest_card_and_modal_without_requester_id(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'title' => 'Guest Scanner Request',
            'requester_id' => null,
            'guest_name' => 'Dr. Alexiou',
            'guest_contact' => '210-5551234',
            'facility_name' => 'Cardiology',
            'status' => 'backlog',
            'tracking_token' => 'TNT-CARD1234',
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => 20,
        ]);

        $this->actingAs($admin);

        Livewire::test(CapacityBoard::class)
            ->assertSee('Guest Scanner Request')
            ->assertSee('Dr. Alexiou')
            ->call('openSlideOver', $postIt->id)
            ->assertSee('#TNT-CARD1234')
            ->assertSee('210-5551234')
            ->assertSee('Cardiology');
    }

    public function test_admin_can_toggle_public_submission_in_workspace_settings(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'test-workspace',
            'public_submission_enabled' => false,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $this->actingAs($admin);

        Livewire::test(CapacityBoard::class)
            ->call('openWorkspaceSettingsModal')
            ->assertSet('publicSubmissionEnabled', false)
            ->set('publicSubmissionEnabled', true)
            ->call('saveWorkspaceSettings')
            ->assertHasNoErrors();

        $this->assertTrue((bool) $tenant->fresh()->public_submission_enabled);
    }
}
