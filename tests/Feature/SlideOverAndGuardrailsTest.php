<?php

namespace Tests\Feature;

use App\Events\BoardUpdated;
use App\Livewire\CapacityBoard;
use App\Models\PostIt;
use App\Models\PostItComment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class SlideOverAndGuardrailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_it_comments_schema_and_relationships(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->worker()->create(['tenant_id' => $tenant->id]);
        $postIt = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $user->id,
        ]);

        $comment = PostItComment::create([
            'post_it_id' => $postIt->id,
            'user_id' => $user->id,
            'body' => 'Checking requirements for this post-it.',
        ]);

        $this->assertDatabaseHas('post_it_comments', [
            'id' => $comment->id,
            'post_it_id' => $postIt->id,
            'user_id' => $user->id,
            'body' => 'Checking requirements for this post-it.',
        ]);

        $this->assertTrue($postIt->comments->contains($comment));
        $this->assertTrue($user->comments->contains($comment));
        $this->assertEquals($postIt->id, $comment->postIt->id);
        $this->assertEquals($user->id, $comment->user->id);
    }

    public function test_role_based_drag_and_drop_guardrails(): void
    {
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create();
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
        $worker1 = User::factory()->worker()->create(['tenant_id' => $tenant->id, 'current_capacity' => 0]);
        $worker2 = User::factory()->worker()->create(['tenant_id' => $tenant->id, 'current_capacity' => 0]);
        $client = User::factory()->client()->create(['tenant_id' => $tenant->id]);

        $backlogPostIt = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client->id,
            'assignee_id' => null,
            'status' => 'backlog',
            'weight' => 2,
        ]);

        $activePostIt = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client->id,
            'assignee_id' => $worker1->id,
            'status' => 'doing',
            'weight' => 3,
        ]);

        // 1. Client CANNOT drag active doing cards or move to done
        $this->actingAs($client);
        Livewire::test(CapacityBoard::class)
            ->call('movePostIt', $activePostIt->id, $worker2->id, 'doing');

        $this->assertEquals($worker1->id, $activePostIt->fresh()->assignee_id);
        $this->assertEquals('doing', $activePostIt->fresh()->status);

        Livewire::test(CapacityBoard::class)
            ->call('movePostIt', $activePostIt->id, $worker1->id, 'done');

        $this->assertEquals('doing', $activePostIt->fresh()->status);

        // 2. Worker2 CANNOT move Worker1's active task
        $this->actingAs($worker2);
        Livewire::test(CapacityBoard::class)
            ->call('movePostIt', $activePostIt->id, $worker2->id, 'doing');

        $this->assertEquals($worker1->id, $activePostIt->fresh()->assignee_id);

        // 3. Worker1 CAN move their own active task to done
        $this->actingAs($worker1);
        Livewire::test(CapacityBoard::class)
            ->call('movePostIt', $activePostIt->id, $worker1->id, 'done');

        $this->assertEquals('done', $activePostIt->fresh()->status);

        // 4. Worker2 CAN pull from backlog into doing
        $this->actingAs($worker2);
        Livewire::test(CapacityBoard::class)
            ->call('movePostIt', $backlogPostIt->id, $worker2->id, 'doing');

        $this->assertEquals('doing', $backlogPostIt->fresh()->status);
        $this->assertEquals($worker2->id, $backlogPostIt->fresh()->assignee_id);

        // 5. Admin CAN move any task
        $this->actingAs($admin);
        Livewire::test(CapacityBoard::class)
            ->call('movePostIt', $backlogPostIt->id, $worker1->id, 'doing');

        $this->assertEquals($worker1->id, $backlogPostIt->fresh()->assignee_id);
    }

    public function test_slide_over_inspection_authorization_and_client_masking(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
        $worker = User::factory()->worker()->create(['tenant_id' => $tenant->id]);
        $client1 = User::factory()->client()->create(['tenant_id' => $tenant->id]);
        $client2 = User::factory()->client()->create(['tenant_id' => $tenant->id]);

        $taskOfClient1 = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client1->id,
            'assignee_id' => $worker->id,
            'status' => 'doing',
            'title' => 'Client 1 Confidential Task',
            'description' => 'Top secret financial data',
        ]);

        $backlogTask = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client1->id,
            'assignee_id' => null,
            'status' => 'backlog',
            'title' => 'Open Backlog Task',
        ]);

        // Client2 attempting to open Client1's task must be blocked
        $this->actingAs($client2);
        Livewire::test(CapacityBoard::class)
            ->call('openSlideOver', $taskOfClient1->id)
            ->assertSet('showSlideOver', false)
            ->assertSet('selectedPostItId', null);

        // Client1 opening their own task is authorized
        $this->actingAs($client1);
        Livewire::test(CapacityBoard::class)
            ->call('openSlideOver', $taskOfClient1->id)
            ->assertSet('showSlideOver', true)
            ->assertSet('selectedPostItId', $taskOfClient1->id)
            ->assertSee('Client 1 Confidential Task')
            ->assertSee('Top secret financial data');

        // Worker inspecting backlog task is authorized
        $this->actingAs($worker);
        Livewire::test(CapacityBoard::class)
            ->call('openSlideOver', $backlogTask->id)
            ->assertSet('showSlideOver', true)
            ->assertSee('Open Backlog Task');

        // Admin can inspect any task
        $this->actingAs($admin);
        Livewire::test(CapacityBoard::class)
            ->call('openSlideOver', $taskOfClient1->id)
            ->assertSet('showSlideOver', true);
    }

    public function test_scope_calibration_recalculates_bounty_and_capacity(): void
    {
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create();
        $worker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'current_capacity' => 1,
            'max_capacity' => 10,
        ]);
        $client = User::factory()->client()->create(['tenant_id' => $tenant->id]);

        $postIt = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client->id,
            'assignee_id' => $worker->id,
            'status' => 'doing',
            'weight' => 1,
            'priority' => 'low',
            'bounty_points' => 8, // 1 * 10 * 0.8
        ]);

        $this->actingAs($worker);

        Livewire::test(CapacityBoard::class)
            ->call('openSlideOver', $postIt->id)
            ->set('slideOverWeight', 4)
            ->set('slideOverPriority', 'urgent')
            ->call('saveScopeCalibration');

        $postIt->refresh();
        $this->assertEquals(4, $postIt->weight);
        $this->assertEquals('urgent', $postIt->priority);
        // 4 * 10 * 1.5 = 60
        $this->assertEquals(60, $postIt->bounty_points);

        // Worker capacity updated from 1 to 4
        $this->assertEquals(4, $worker->fresh()->current_capacity);

        Event::assertDispatched(BoardUpdated::class);
    }

    public function test_coop_assist_configuration_and_micro_comments_thread(): void
    {
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create();
        $assignee = User::factory()->worker()->create(['tenant_id' => $tenant->id]);
        $helper = User::factory()->worker()->create(['tenant_id' => $tenant->id]);
        $client = User::factory()->client()->create(['tenant_id' => $tenant->id]);

        $postIt = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client->id,
            'assignee_id' => $assignee->id,
            'status' => 'doing',
            'weight' => 2,
            'priority' => 'normal',
            'bounty_points' => 20,
        ]);

        // Assignee configures Co-op helper and split
        $this->actingAs($assignee);
        Livewire::test(CapacityBoard::class)
            ->call('openSlideOver', $postIt->id)
            ->set('slideOverHelperId', $helper->id)
            ->set('slideOverHelperSplit', 35)
            ->call('saveSlideOverCoOp');

        $postIt->refresh();
        $this->assertEquals($helper->id, $postIt->helper_id);
        $this->assertEquals(35, $postIt->helper_split);

        // Assignee adds a comment
        Livewire::test(CapacityBoard::class)
            ->call('openSlideOver', $postIt->id)
            ->set('newCommentBody', 'I have initiated pairing with helper for this sprint.')
            ->call('addComment')
            ->assertSet('newCommentBody', '');

        $this->assertDatabaseHas('post_it_comments', [
            'post_it_id' => $postIt->id,
            'user_id' => $assignee->id,
            'body' => 'I have initiated pairing with helper for this sprint.',
        ]);

        // Helper adds a response comment
        $this->actingAs($helper);
        Livewire::test(CapacityBoard::class)
            ->call('openSlideOver', $postIt->id)
            ->set('newCommentBody', 'Acknowledged, reviewing the code now.')
            ->call('addComment');

        $this->assertDatabaseHas('post_it_comments', [
            'post_it_id' => $postIt->id,
            'user_id' => $helper->id,
            'body' => 'Acknowledged, reviewing the code now.',
        ]);

        $this->assertCount(2, $postIt->fresh()->comments);
    }

    public function test_canceling_backlog_request(): void
    {
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create();
        $client = User::factory()->client()->create(['tenant_id' => $tenant->id]);

        $postIt = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client->id,
            'status' => 'backlog',
            'title' => 'Accidental duplicate task',
        ]);

        $this->actingAs($client);
        Livewire::test(CapacityBoard::class)
            ->call('openSlideOver', $postIt->id)
            ->call('cancelPostIt')
            ->assertSet('showSlideOver', false);

        $this->assertDatabaseMissing('post_its', [
            'id' => $postIt->id,
        ]);
    }
}
