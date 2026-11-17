<?php

namespace Tests\Feature;

use App\Events\BoardUpdated;
use App\Livewire\CapacityBoard;
use App\Livewire\PublicTrackRequest;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class RequesterCloseTest extends TestCase
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

    public function test_client_can_self_close_own_backlog_request_with_zero_bounty_points(): void
    {
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create();
        $client = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'client',
            'name' => 'Maria Client',
            'facility_name' => 'Unit Alpha',
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client->id,
            'facility_name' => 'Unit Alpha',
            'title' => 'Need printer paper',
            'description' => 'Tray 2 is empty.',
            'status' => 'backlog',
            'priority' => 'low',
            'weight' => 2,
            'bounty_points' => 25,
            'tracking_token' => 'TNT-TEST0001',
        ]);

        $this->actingAs($client);

        Livewire::test(CapacityBoard::class)
            ->call('closeByRequester', $postIt->id, 'Found paper in the cabinet')
            ->assertHasNoErrors();

        $postIt->refresh();

        $this->assertSame('done', $postIt->status);
        $this->assertNotNull($postIt->completed_at);
        $this->assertSame(0, $postIt->bounty_points);
        $this->assertTrue($postIt->isClosedByRequester());

        // Check transfer audit
        $this->assertDatabaseHas('post_it_transfers', [
            'post_it_id' => $postIt->id,
            'actor_id' => $client->id,
            'action' => 'closed_by_requester',
        ]);

        // Check comment
        $this->assertDatabaseHas('post_it_comments', [
            'post_it_id' => $postIt->id,
            'user_id' => $client->id,
        ]);
        $comment = $postIt->comments()->latest()->first();
        $this->assertStringContainsString('Maria Client', $comment->body);
        $this->assertStringContainsString('Found paper in the cabinet', $comment->body);

        Event::assertDispatched(BoardUpdated::class);
    }

    public function test_client_can_self_close_assigned_request_and_frees_technician_capacity_without_awarding_points(): void
    {
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create();
        $client = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'client',
            'facility_name' => 'Unit Beta',
        ]);

        $tech = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'worker',
            'total_points' => 100,
            'max_capacity' => 10,
            'current_capacity' => 3,
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client->id,
            'assignee_id' => $tech->id,
            'facility_name' => 'Unit Beta',
            'title' => 'Monitor flicker',
            'status' => 'assigned',
            'priority' => 'normal',
            'weight' => 3,
            'bounty_points' => 30,
            'tracking_token' => 'TNT-TEST0002',
        ]);

        $this->actingAs($client);

        Livewire::test(CapacityBoard::class)
            ->call('closeByRequester', $postIt->id)
            ->assertHasNoErrors();

        $postIt->refresh();
        $tech->refresh();

        $this->assertSame('done', $postIt->status);
        $this->assertSame(0, $postIt->bounty_points);
        $this->assertSame(100, $tech->total_points, 'Technician should not farm unearned points for unstarted work');
        $this->assertSame(0, $tech->current_capacity, 'Technician capacity should be decremented back to 0');
    }

    public function test_client_self_closing_in_progress_doing_request_awards_technician_bounty(): void
    {
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create();
        $client = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'client',
        ]);

        $tech = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'worker',
            'total_points' => 50,
            'max_capacity' => 10,
            'current_capacity' => 2,
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client->id,
            'assignee_id' => $tech->id,
            'title' => 'Network drop replacement',
            'status' => 'doing',
            'started_at' => now()->subMinutes(15),
            'priority' => 'high',
            'weight' => 2,
            'bounty_points' => 40,
            'tracking_token' => 'TNT-TEST0003',
        ]);

        $this->actingAs($client);

        Livewire::test(CapacityBoard::class)
            ->call('closeByRequester', $postIt->id, 'Tested and resolved with technician')
            ->assertHasNoErrors();

        $postIt->refresh();
        $tech->refresh();

        $this->assertSame('done', $postIt->status);
        $this->assertGreaterThan(50, $tech->total_points, 'Technician should receive bounty points for work already in progress');
        $this->assertSame(0, $tech->current_capacity);
    }

    public function test_same_facility_client_can_close_request_from_colleague(): void
    {
        $tenant = Tenant::factory()->create();
        $colleague = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'client',
            'facility_name' => 'Cardiology Clinic',
        ]);

        $client = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'client',
            'facility_name' => 'Cardiology Clinic',
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'requester_id' => $colleague->id,
            'facility_name' => 'Cardiology Clinic',
            'title' => 'ECG Machine Cable issue',
            'status' => 'backlog',
            'priority' => 'urgent',
            'weight' => 3,
            'bounty_points' => 45,
            'tracking_token' => 'TNT-TEST0004',
        ]);

        $this->actingAs($client);

        Livewire::test(CapacityBoard::class)
            ->call('closeByRequester', $postIt->id, 'Replaced cable from spare storage')
            ->assertHasNoErrors();

        $postIt->refresh();
        $this->assertSame('done', $postIt->status);
    }

    public function test_client_cannot_close_request_from_different_facility_or_user(): void
    {
        $tenant = Tenant::factory()->create();
        $otherClient = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'client',
            'facility_name' => 'Neurology Clinic',
        ]);

        $client = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'client',
            'facility_name' => 'Cardiology Clinic',
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'requester_id' => $otherClient->id,
            'facility_name' => 'Neurology Clinic',
            'title' => 'MRI Workstation issue',
            'status' => 'backlog',
            'priority' => 'urgent',
            'weight' => 4,
            'bounty_points' => 50,
            'tracking_token' => 'TNT-TEST0005',
        ]);

        $this->actingAs($client);

        Livewire::test(CapacityBoard::class)
            ->call('closeByRequester', $postIt->id)
            ->assertStatus(403);

        $postIt->refresh();
        $this->assertSame('backlog', $postIt->status);
    }

    public function test_guest_can_self_close_request_via_public_tracking_link(): void
    {
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create([
            'public_submission_enabled' => true,
        ]);

        $tech = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'worker',
            'current_capacity' => 2,
            'max_capacity' => 10,
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'requester_id' => null,
            'guest_name' => 'Nikos Guest',
            'guest_contact' => 'Ext 401',
            'facility_name' => 'Radiology',
            'title' => 'Workstation rebooting',
            'status' => 'assigned',
            'assignee_id' => $tech->id,
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => 20,
            'tracking_token' => 'TNT-GUEST001',
        ]);

        Livewire::test(PublicTrackRequest::class, ['token' => 'TNT-GUEST001'])
            ->assertSee('Issue Resolved — Close Request')
            ->set('guestCloseReason', 'Turned off surge protector and back on, all good')
            ->call('closeByGuest')
            ->assertHasNoErrors()
            ->assertSee('Request Canceled');

        $postIt->refresh();
        $tech->refresh();

        $this->assertSame('done', $postIt->status);
        $this->assertSame(0, $postIt->bounty_points);
        $this->assertSame(0, $tech->current_capacity);
        $this->assertTrue($postIt->isClosedByRequester());

        // Check comment
        $this->assertDatabaseHas('post_it_comments', [
            'post_it_id' => $postIt->id,
            'user_id' => null,
        ]);

        $comment = $postIt->comments()->latest()->first();
        $this->assertStringContainsString('Nikos Guest', $comment->body);
        $this->assertStringContainsString('Turned off surge protector', $comment->body);

        Event::assertDispatched(BoardUpdated::class);
    }

    public function test_cannot_close_already_completed_request(): void
    {
        $tenant = Tenant::factory()->create();
        $client = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'client',
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client->id,
            'title' => 'Finished item',
            'status' => 'done',
            'completed_at' => now(),
            'tracking_token' => 'TNT-DONE999',
        ]);

        $this->actingAs($client);

        Livewire::test(CapacityBoard::class)
            ->call('closeByRequester', $postIt->id)
            ->assertStatus(403);

        Livewire::test(PublicTrackRequest::class, ['token' => 'TNT-DONE999'])
            ->call('closeByGuest')
            ->assertDontSee('Issue Resolved — Close Request');
    }
}
