<?php

namespace Tests\Feature;

use App\Livewire\CapacityBoard;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\PostItTransfer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Step9BFirewallAndTransferChainTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Department $department;

    protected User $admin;

    protected User $coordinator;

    protected User $publicWorker;

    protected User $internalWorker;

    protected User $awayWorker;

    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['name' => 'Hellenic Ops']);
        $this->department = Department::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Software Dept',
        ]);

        $this->admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Alex Admin',
            'board_visibility' => 'private',
            'is_available' => true,
        ]);

        $this->coordinator = User::factory()->coordinator()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Nikos Coordinator',
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        $this->publicWorker = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Elena Public',
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        $this->internalWorker = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Maria Internal',
            'board_visibility' => 'internal',
            'is_available' => true,
        ]);

        $this->awayWorker = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Giorgos Away',
            'board_visibility' => 'public',
            'is_available' => false,
        ]);

        $this->client = User::factory()->client()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => null,
            'name' => 'Sofia Client',
            'board_visibility' => 'none',
            'is_available' => true,
        ]);
    }

    public function test_away_collaborators_are_completely_hidden_from_client_users(): void
    {
        // When viewed by client, away worker is excluded from columns and available collaborators
        Livewire::actingAs($this->client)
            ->test(CapacityBoard::class)
            ->assertViewHas('workers', function ($workers) {
                // Must contain active public workers, but NOT awayWorker and NOT internalWorker
                $workerIds = $workers->pluck('id')->all();

                return in_array($this->publicWorker->id, $workerIds, true)
                    && in_array($this->coordinator->id, $workerIds, true)
                    && ! in_array($this->awayWorker->id, $workerIds, true)
                    && ! in_array($this->internalWorker->id, $workerIds, true);
            })
            ->assertViewHas('availableCollaborators', function ($collabs) {
                $collabIds = $collabs->pluck('id')->all();

                return ! in_array($this->awayWorker->id, $collabIds, true);
            });

        // When viewed by internal staff, away worker is still visible (dimmed with AWAY badge)
        Livewire::actingAs($this->coordinator)
            ->test(CapacityBoard::class)
            ->assertViewHas('workers', function ($workers) {
                $workerIds = $workers->pluck('id')->all();

                return in_array($this->awayWorker->id, $workerIds, true)
                    && in_array($this->internalWorker->id, $workerIds, true);
            })
            ->assertSee('AWAY');
    }

    public function test_strict_firewall_blocks_client_requests_from_entering_internal_columns(): void
    {
        // 1. Client user cannot create a card assigned to internal worker
        Livewire::actingAs($this->client)
            ->test(CapacityBoard::class)
            ->set('title', 'Client task attempt on internal')
            ->set('priority', 'normal')
            ->set('weight', 2)
            ->set('assigneeId', $this->internalWorker->id)
            ->call('createPostIt')
            ->assertHasErrors(['assigneeId']);

        // 2. Client-originated card in backlog
        $clientCard = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->client->id,
            'assignee_id' => null,
            'status' => 'backlog',
            'weight' => 2,
        ]);

        // 3. Admin attempts to move clientCard onto internalWorker's column -> FIREWALL BLOCKS
        Livewire::actingAs($this->admin)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $clientCard->id, $this->internalWorker->id, 'assigned');

        $this->assertDatabaseHas('post_its', [
            'id' => $clientCard->id,
            'status' => 'backlog',
            'assignee_id' => null,
        ]);

        // 4. Coordinator attempts to move clientCard onto internalWorker -> FIREWALL BLOCKS
        Livewire::actingAs($this->coordinator)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $clientCard->id, $this->internalWorker->id, 'assigned');

        $this->assertDatabaseHas('post_its', [
            'id' => $clientCard->id,
            'status' => 'backlog',
            'assignee_id' => null,
        ]);

        // 5. But moving clientCard to a public worker is permitted
        Livewire::actingAs($this->coordinator)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $clientCard->id, $this->publicWorker->id, 'assigned');

        $this->assertDatabaseHas('post_its', [
            'id' => $clientCard->id,
            'status' => 'assigned',
            'assignee_id' => $this->publicWorker->id,
        ]);

        // 6. An internal task (created by coordinator) CAN be moved to internalWorker by admin
        $internalTask = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->coordinator->id,
            'assignee_id' => null,
            'status' => 'backlog',
            'weight' => 2,
        ]);

        Livewire::actingAs($this->admin)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $internalTask->id, $this->internalWorker->id, 'assigned');

        $this->assertDatabaseHas('post_its', [
            'id' => $internalTask->id,
            'status' => 'assigned',
            'assignee_id' => $this->internalWorker->id,
        ]);
    }

    public function test_open_collaborator_attribution_rules(): void
    {
        // 1. Card created by internal staff (Elena Public Worker)
        $internalCard = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->publicWorker->id,
            'assignee_id' => $this->coordinator->id,
            'status' => 'assigned',
            'title' => 'Internal architecture RFC',
        ]);

        // When viewed by client, the requester name is OPEN (not anonymized)
        Livewire::actingAs($this->client)
            ->test(CapacityBoard::class)
            ->assertSee('Elena Public');

        // 2. Card created by Sofia Client
        $clientCard = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->client->id,
            'assignee_id' => $this->publicWorker->id,
            'assigned_by_id' => $this->coordinator->id,
            'status' => 'assigned',
            'title' => 'Client specific report request',
        ]);

        $anotherClient = User::factory()->client()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Other Client',
        ]);

        // When viewed by another client, requester is anonymized but forwarded by is displayed
        Livewire::actingAs($anotherClient)
            ->test(CapacityBoard::class)
            ->assertSee('Ανώνυμο Αίτημα')
            ->assertSee('Ανάθεση από:')
            ->assertSee('Nikos Coordinator');
    }

    public function test_post_it_transfer_recording_and_workflow_trail(): void
    {
        // 1. Initial creation with assignee records an 'assigned' transfer
        Livewire::actingAs($this->coordinator)
            ->test(CapacityBoard::class)
            ->set('title', 'Brand new handoff task')
            ->set('priority', 'normal')
            ->set('weight', 2)
            ->set('assigneeId', $this->publicWorker->id)
            ->call('createPostIt');

        $postIt = PostIt::where('tenant_id', $this->tenant->id)->where('title', 'Brand new handoff task')->firstOrFail();

        $this->assertEquals($this->coordinator->id, $postIt->assigned_by_id);
        $this->assertDatabaseHas('post_it_transfers', [
            'post_it_id' => $postIt->id,
            'actor_id' => $this->coordinator->id,
            'from_user_id' => null,
            'to_user_id' => $this->publicWorker->id,
            'action' => 'assigned',
        ]);

        // 2. Reassignment from Elena (publicWorker) to Nikos (coordinator)
        Livewire::actingAs($this->coordinator)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $postIt->id, $this->coordinator->id, 'assigned');

        $this->assertDatabaseHas('post_it_transfers', [
            'post_it_id' => $postIt->id,
            'actor_id' => $this->coordinator->id,
            'from_user_id' => $this->publicWorker->id,
            'to_user_id' => $this->coordinator->id,
            'action' => 'transferred',
        ]);

        // 3. Start task
        Livewire::actingAs($this->coordinator)
            ->test(CapacityBoard::class)
            ->call('startPostIt', $postIt->id);

        $this->assertDatabaseHas('post_it_transfers', [
            'post_it_id' => $postIt->id,
            'actor_id' => $this->coordinator->id,
            'action' => 'started',
        ]);

        // 4. Complete task
        Livewire::actingAs($this->coordinator)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $postIt->id, $this->coordinator->id, 'done');

        $this->assertDatabaseHas('post_it_transfers', [
            'post_it_id' => $postIt->id,
            'actor_id' => $this->coordinator->id,
            'action' => 'completed',
        ]);
    }

    public function test_assignment_chain_accessor_and_visual_badge(): void
    {
        $task = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->admin->id,
            'assignee_id' => $this->coordinator->id,
            'status' => 'done',
        ]);

        // Simulate multi-hop chain: Nikos -> Elena -> Nikos
        PostItTransfer::create([
            'post_it_id' => $task->id,
            'actor_id' => $this->admin->id,
            'from_user_id' => null,
            'to_user_id' => $this->coordinator->id,
            'action' => 'assigned',
            'created_at' => now()->subDays(3),
        ]);
        PostItTransfer::create([
            'post_it_id' => $task->id,
            'actor_id' => $this->coordinator->id,
            'from_user_id' => $this->coordinator->id,
            'to_user_id' => $this->publicWorker->id,
            'action' => 'transferred',
            'created_at' => now()->subDays(2),
        ]);
        PostItTransfer::create([
            'post_it_id' => $task->id,
            'actor_id' => $this->publicWorker->id,
            'from_user_id' => $this->publicWorker->id,
            'to_user_id' => $this->coordinator->id,
            'action' => 'transferred',
            'created_at' => now()->subDay(),
        ]);

        $task->refresh();
        $chain = $task->getAssignmentChain();

        $this->assertEquals(['Nikos', 'Elena', 'Nikos'], $chain);

        // Verify board render contains the chain badge
        Livewire::actingAs($this->admin)
            ->test(CapacityBoard::class)
            ->assertSee('Nikos → Elena → Nikos')
            ->call('openSlideOver', $task->id)
            ->assertSee('Assignment & Workflow Chain');
    }
}
