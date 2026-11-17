<?php

namespace Tests\Feature;

use App\Events\BoardUpdated;
use App\Livewire\CapacityBoard;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class Step24DropIntoCompletedTest extends TestCase
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

    public function test_dropping_doing_card_into_completed_marks_done_is_canceled_false_and_awards_bounty(): void
    {
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create();
        $tech = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'total_points' => 100,
            'current_capacity' => 3,
            'max_capacity' => 10,
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'title' => 'Server Maintenance',
            'status' => 'doing',
            'assignee_id' => $tech->id,
            'weight' => 3,
            'priority' => 'high',
            'bounty_points' => 50,
            'started_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($tech);

        Livewire::test(CapacityBoard::class)
            ->call('dropIntoCompleted', $postIt->id)
            ->assertHasNoErrors();

        $postIt->refresh();
        $tech->refresh();

        $this->assertSame('done', $postIt->status);
        $this->assertFalse($postIt->is_canceled);
        $this->assertNotNull($postIt->completed_at);
        $this->assertGreaterThan(100, $tech->total_points, 'Technician should receive bounty points for completed in-progress task');
        $this->assertSame(0, $tech->current_capacity, 'Technician capacity should be decremented');

        $this->assertDatabaseHas('post_it_transfers', [
            'post_it_id' => $postIt->id,
            'actor_id' => $tech->id,
            'action' => 'completed',
        ]);

        Event::assertDispatched(BoardUpdated::class);
    }

    public function test_dropping_assigned_card_into_completed_marks_done_is_canceled_true_and_awards_zero_points(): void
    {
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create();
        $tech = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'total_points' => 100,
            'current_capacity' => 2,
            'max_capacity' => 10,
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'title' => 'Unstarted Printer Fix',
            'status' => 'assigned',
            'assignee_id' => $tech->id,
            'weight' => 2,
            'priority' => 'normal',
            'bounty_points' => 30,
            'started_at' => null,
        ]);

        $this->actingAs($tech);

        Livewire::test(CapacityBoard::class)
            ->call('dropIntoCompleted', $postIt->id)
            ->assertHasNoErrors();

        $postIt->refresh();
        $tech->refresh();

        $this->assertSame('done', $postIt->status);
        $this->assertTrue($postIt->is_canceled);
        $this->assertSame(0, $postIt->bounty_points);
        $this->assertNotNull($postIt->completed_at);
        $this->assertSame(100, $tech->total_points, 'Technician must NOT receive points for an unstarted card');
        $this->assertSame(0, $tech->current_capacity, 'Technician capacity should be freed');

        $this->assertDatabaseHas('post_it_transfers', [
            'post_it_id' => $postIt->id,
            'actor_id' => $tech->id,
            'action' => 'canceled',
        ]);

        $this->assertDatabaseHas('post_it_comments', [
            'post_it_id' => $postIt->id,
            'user_id' => $tech->id,
        ]);

        Event::assertDispatched(BoardUpdated::class);
    }

    public function test_dropping_backlog_card_into_completed_cancels_with_zero_points(): void
    {
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create();
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'title' => 'Backlog item no longer required',
            'status' => 'backlog',
            'assignee_id' => null,
            'weight' => 1,
            'priority' => 'low',
            'bounty_points' => 15,
        ]);

        $this->actingAs($admin);

        Livewire::test(CapacityBoard::class)
            ->call('dropIntoCompleted', $postIt->id)
            ->assertHasNoErrors();

        $postIt->refresh();

        $this->assertSame('done', $postIt->status);
        $this->assertTrue($postIt->is_canceled);
        $this->assertSame(0, $postIt->bounty_points);

        $this->assertDatabaseHas('post_it_transfers', [
            'post_it_id' => $postIt->id,
            'actor_id' => $admin->id,
            'action' => 'canceled',
        ]);

        Event::assertDispatched(BoardUpdated::class);
    }

    public function test_client_can_drop_own_unstarted_card_into_completed(): void
    {
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create();
        $client = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'client',
        ]);

        $tech = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'current_capacity' => 2,
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client->id,
            'title' => 'Client Cancel Request',
            'status' => 'assigned',
            'assignee_id' => $tech->id,
            'weight' => 2,
            'bounty_points' => 25,
            'started_at' => null,
        ]);

        $this->actingAs($client);

        Livewire::test(CapacityBoard::class)
            ->call('dropIntoCompleted', $postIt->id)
            ->assertHasNoErrors();

        $postIt->refresh();
        $tech->refresh();

        $this->assertSame('done', $postIt->status);
        $this->assertTrue($postIt->is_canceled);
        $this->assertSame(0, $postIt->bounty_points);
        $this->assertSame(0, $tech->current_capacity);
    }

    public function test_unauthorized_user_cannot_drop_someone_elses_card_into_completed(): void
    {
        $tenant = Tenant::factory()->create();
        $worker1 = User::factory()->worker()->create(['tenant_id' => $tenant->id]);
        $worker2 = User::factory()->worker()->create(['tenant_id' => $tenant->id]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'title' => 'Worker1 Card',
            'status' => 'assigned',
            'assignee_id' => $worker1->id,
            'weight' => 2,
            'bounty_points' => 30,
        ]);

        // Worker2 tries to drop Worker1's card
        $this->actingAs($worker2);

        Livewire::test(CapacityBoard::class)
            ->call('dropIntoCompleted', $postIt->id)
            ->assertStatus(403);

        $postIt->refresh();
        $this->assertSame('assigned', $postIt->status);
    }

    public function test_coordinator_can_drop_department_card_into_completed(): void
    {
        $tenant = Tenant::factory()->create();
        $department = Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'IT Department',
            'slug' => 'it-dept',
            'color' => '#3b82f6',
        ]);

        $coord = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'coordinator',
            'department_id' => $department->id,
        ]);

        $worker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'current_capacity' => 2,
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'title' => 'Department Task',
            'status' => 'assigned',
            'assignee_id' => $worker->id,
            'weight' => 2,
            'bounty_points' => 20,
        ]);

        $this->actingAs($coord);

        Livewire::test(CapacityBoard::class)
            ->call('dropIntoCompleted', $postIt->id)
            ->assertHasNoErrors();

        $postIt->refresh();
        $this->assertSame('done', $postIt->status);
        $this->assertTrue($postIt->is_canceled);
    }
}
