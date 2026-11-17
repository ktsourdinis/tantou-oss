<?php

namespace Tests\Feature;

use App\Livewire\CapacityBoard;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\PostItTransfer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BountyCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkflowAndBountyTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Department $department;

    protected User $admin;

    protected User $coordinator;

    protected User $worker1;

    protected User $worker2;

    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['name' => 'Tantou Workflow Corp']);
        $this->department = Department::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Engineering',
            'color' => '#10b981',
        ]);

        $this->admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'George Admin',
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        $this->coordinator = User::factory()->coordinator()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Stavros Coordinator',
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        $this->worker1 = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Petros Assignee',
            'board_visibility' => 'public',
            'is_available' => true,
            'current_capacity' => 0,
            'total_points' => 100,
        ]);

        $this->worker2 = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Katerina Helper',
            'board_visibility' => 'public',
            'is_available' => true,
            'current_capacity' => 0,
            'total_points' => 50,
        ]);

        $this->client = User::factory()->client()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => null,
            'name' => 'Dimitra Client',
            'board_visibility' => 'none',
            'is_available' => true,
        ]);
    }

    /**
     * Verify the two-stage workflow ('assigned' vs 'doing'):
     * - A client can move their own unstarted ('assigned', started_at === null) post-it
     *   to another available public collaborator or backlog.
     * - Once a collaborator starts work ('doing', started_at !== null), the card is locked
     *   and client moves are blocked.
     */
    public function test_two_stage_workflow_and_client_reassignment_rules(): void
    {
        // 1. Client creates card assigned to worker1 (starts as 'assigned', started_at is null)
        Livewire::actingAs($this->client)
            ->test(CapacityBoard::class)
            ->set('title', 'Client Feature Request')
            ->set('priority', 'high')
            ->set('weight', 3)
            ->set('assigneeId', $this->worker1->id)
            ->call('createPostIt');

        $postIt = PostIt::where('tenant_id', $this->tenant->id)
            ->where('title', 'Client Feature Request')
            ->firstOrFail();

        $this->assertEquals('assigned', $postIt->status);
        $this->assertNull($postIt->started_at);
        $this->assertEquals($this->worker1->id, $postIt->assignee_id);
        $this->assertEquals(3, $this->worker1->fresh()->current_capacity);

        // 2. Client reassigns unstarted card to worker2 -> SUCCEEDS
        Livewire::actingAs($this->client)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $postIt->id, $this->worker2->id, 'assigned');

        $this->assertEquals($this->worker2->id, $postIt->fresh()->assignee_id);
        $this->assertEquals('assigned', $postIt->fresh()->status);
        $this->assertEquals(0, $this->worker1->fresh()->current_capacity);
        $this->assertEquals(3, $this->worker2->fresh()->current_capacity);

        // 3. Client moves card back to backlog -> SUCCEEDS
        Livewire::actingAs($this->client)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $postIt->id, null, 'backlog');

        $this->assertEquals('backlog', $postIt->fresh()->status);
        $this->assertNull($postIt->fresh()->assignee_id);
        $this->assertEquals(0, $this->worker2->fresh()->current_capacity);

        // 4. Coordinator moves it to worker1, and worker1 starts work
        Livewire::actingAs($this->coordinator)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $postIt->id, $this->worker1->id, 'assigned');

        Livewire::actingAs($this->worker1)
            ->test(CapacityBoard::class)
            ->call('startPostIt', $postIt->id);

        $freshStarted = $postIt->fresh();
        $this->assertEquals('doing', $freshStarted->status);
        $this->assertNotNull($freshStarted->started_at);

        // 5. Client attempts to move or reassign the card while it is 'doing' -> BLOCKED
        Livewire::actingAs($this->client)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $postIt->id, $this->worker2->id, 'assigned');

        // Card remains locked on worker1 in 'doing' state
        $freshLocked = $postIt->fresh();
        $this->assertEquals($this->worker1->id, $freshLocked->assignee_id);
        $this->assertEquals('doing', $freshLocked->status);
    }

    /**
     * Verify that collaborator handoffs create PostItTransfer audit records in sequence.
     */
    public function test_collaborator_handoffs_record_post_it_transfers_in_audit_sequence(): void
    {
        // 1. Initial creation with direct assignment to worker1
        Livewire::actingAs($this->coordinator)
            ->test(CapacityBoard::class)
            ->set('title', 'Sequential Handoff Ticket')
            ->set('priority', 'normal')
            ->set('weight', 2)
            ->set('assigneeId', $this->worker1->id)
            ->call('createPostIt');

        $postIt = PostIt::where('tenant_id', $this->tenant->id)
            ->where('title', 'Sequential Handoff Ticket')
            ->firstOrFail();

        $this->assertDatabaseHas('post_it_transfers', [
            'post_it_id' => $postIt->id,
            'actor_id' => $this->coordinator->id,
            'from_user_id' => null,
            'to_user_id' => $this->worker1->id,
            'action' => 'assigned',
        ]);

        // 2. Reassignment from worker1 to worker2 by coordinator
        Livewire::actingAs($this->coordinator)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $postIt->id, $this->worker2->id, 'assigned');

        $this->assertDatabaseHas('post_it_transfers', [
            'post_it_id' => $postIt->id,
            'actor_id' => $this->coordinator->id,
            'from_user_id' => $this->worker1->id,
            'to_user_id' => $this->worker2->id,
            'action' => 'transferred',
        ]);

        // 3. Worker2 starts work
        Livewire::actingAs($this->worker2)
            ->test(CapacityBoard::class)
            ->call('startPostIt', $postIt->id);

        $this->assertDatabaseHas('post_it_transfers', [
            'post_it_id' => $postIt->id,
            'actor_id' => $this->worker2->id,
            'from_user_id' => $this->worker2->id,
            'to_user_id' => $this->worker2->id,
            'action' => 'started',
        ]);

        // 4. Worker2 completes the card
        Livewire::actingAs($this->worker2)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $postIt->id, $this->worker2->id, 'done');

        $this->assertDatabaseHas('post_it_transfers', [
            'post_it_id' => $postIt->id,
            'actor_id' => $this->worker2->id,
            'from_user_id' => $this->worker2->id,
            'to_user_id' => $this->worker2->id,
            'action' => 'completed',
        ]);

        // Verify total sequence count and ordered actions
        $transfers = PostItTransfer::where('post_it_id', $postIt->id)->orderBy('id')->get();
        $this->assertCount(4, $transfers);
        $this->assertEquals(['assigned', 'transferred', 'started', 'completed'], $transfers->pluck('action')->all());

        // Verify assignment chain accessor and rendered badge
        $chain = $postIt->fresh()->getAssignmentChain();
        $this->assertEquals(['Petros', 'Katerina'], $chain);

        Livewire::actingAs($this->coordinator)
            ->test(CapacityBoard::class)
            ->assertSee('Petros → Katerina');
    }

    /**
     * Verify BountyCalculator and Co-op split:
     * - A collaborator cannot set themselves as their own helper (helper_id !== assignee_id).
     * - Moving a card to backlog resets helper_id, helper_split, and started_at.
     */
    public function test_co_op_collaborator_restrictions_self_assignment_and_backlog_reset(): void
    {
        $postIt = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->coordinator->id,
            'assignee_id' => $this->worker1->id,
            'status' => 'assigned',
            'weight' => 3,
            'helper_id' => null,
            'helper_split' => 0,
        ]);

        // 1. Worker1 attempts to assign themselves as their own helper -> BLOCKED
        Livewire::actingAs($this->worker1)
            ->test(CapacityBoard::class)
            ->call('openSlideOver', $postIt->id)
            ->set('slideOverHelperId', $this->worker1->id)
            ->set('slideOverHelperSplit', 30)
            ->call('saveSlideOverCoOp');

        $this->assertNull($postIt->fresh()->helper_id);
        $this->assertEquals(0, $postIt->fresh()->helper_split);

        // Also test modal saveCoOpSplit method for self-helper prevention
        Livewire::actingAs($this->worker1)
            ->test(CapacityBoard::class)
            ->set('coOpPostItId', $postIt->id)
            ->set('coOpHelperId', $this->worker1->id)
            ->set('coOpHelperSplit', 25)
            ->call('saveCoOpSplit');

        $this->assertNull($postIt->fresh()->helper_id);

        // 2. Assigning an eligible public coworker (worker2) -> SUCCEEDS
        Livewire::actingAs($this->worker1)
            ->test(CapacityBoard::class)
            ->call('openSlideOver', $postIt->id)
            ->set('slideOverHelperId', $this->worker2->id)
            ->set('slideOverHelperSplit', 35)
            ->call('saveSlideOverCoOp');

        $this->assertEquals($this->worker2->id, $postIt->fresh()->helper_id);
        $this->assertEquals(35, $postIt->fresh()->helper_split);

        // 3. Moving card back to backlog -> AUTOMATICALLY RESETS helper and audit unassign
        Livewire::actingAs($this->worker1)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $postIt->id, null, 'backlog');

        $freshBacklog = $postIt->fresh();
        $this->assertEquals('backlog', $freshBacklog->status);
        $this->assertNull($freshBacklog->assignee_id);
        $this->assertNull($freshBacklog->helper_id);
        $this->assertEquals(0, $freshBacklog->helper_split);
        $this->assertNull($freshBacklog->started_at);
        $this->assertNull($freshBacklog->assigned_by_id);

        $this->assertDatabaseHas('post_it_transfers', [
            'post_it_id' => $postIt->id,
            'actor_id' => $this->worker1->id,
            'from_user_id' => $this->worker1->id,
            'to_user_id' => null,
            'action' => 'unassigned',
        ]);
    }

    /**
     * Verify BountyCalculator and Co-op split completion:
     * - Completing a card ('done') accurately splits bounty_points between assignee and helper.
     * - Subsequent moves or transitions do NOT double-pay.
     */
    public function test_bounty_points_calculation_and_co_op_split_payout_without_double_paying(): void
    {
        // 1. Calculate expected bounty points for weight 3, urgent priority
        $calculator = app(BountyCalculator::class);
        $expectedBounty = $calculator->calculate(weight: 3, priority: 'urgent', tenantId: $this->tenant->id);
        // Base: 3 * 10 = 30. Urgent multiplier: 1.5 => 45 points.
        $this->assertEquals(45, $expectedBounty);

        // 2. Create card with 40% helper split
        $postIt = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->coordinator->id,
            'assignee_id' => $this->worker1->id,
            'helper_id' => $this->worker2->id,
            'helper_split' => 40,
            'status' => 'doing',
            'weight' => 3,
            'priority' => 'urgent',
            'bounty_points' => $expectedBounty,
            'started_at' => now()->subHours(2),
        ]);

        // Helper split: 45 * 0.40 = 18 points for helper, 45 - 18 = 27 points for assignee
        $split = $calculator->calculatePayoutSplit($postIt);
        $this->assertEquals(27, $split['assignee_points']);
        $this->assertEquals(18, $split['helper_points']);

        $initialWorker1Points = $this->worker1->total_points; // 100
        $initialWorker2Points = $this->worker2->total_points; // 50

        // 3. Worker1 completes the task (moves from 'doing' -> 'done')
        Livewire::actingAs($this->worker1)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $postIt->id, $this->worker1->id, 'done');

        $this->assertEquals('done', $postIt->fresh()->status);
        $this->assertNotNull($postIt->fresh()->completed_at);

        // Verify points incremented correctly
        $this->assertEquals($initialWorker1Points + 27, $this->worker1->fresh()->total_points); // 127
        $this->assertEquals($initialWorker2Points + 18, $this->worker2->fresh()->total_points); // 68

        // 4. Subsequent move while already in 'done' state -> DOES NOT DOUBLE PAY
        Livewire::actingAs($this->worker1)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $postIt->id, $this->worker1->id, 'done');

        $this->assertEquals($initialWorker1Points + 27, $this->worker1->fresh()->total_points);
        $this->assertEquals($initialWorker2Points + 18, $this->worker2->fresh()->total_points);
    }

    /**
     * Verify smart deterministic queue ordering:
     * - 'doing' card ranks above 'assigned' cards (regardless of priority).
     * - 'urgent' ranks above 'high'/'normal'/'low'.
     * - Equal priority cards order by created_at ASC (FIFO tie-breaker).
     * - 'done' cards are placed at the bottom, ordered by completed_at DESC.
     * - Livewire re-renders immediately preserve smart queue positions.
     */
    public function test_smart_deterministic_queue_ordering(): void
    {
        // 1. Create a set of cards assigned to worker1 with distinct statuses, priorities, and timestamps
        $doingLow = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->coordinator->id,
            'assignee_id' => $this->worker1->id,
            'status' => 'doing',
            'priority' => 'low',
            'created_at' => now()->subHours(1),
            'started_at' => now()->subMinutes(30),
        ]);

        $assignedUrgentOlder = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->coordinator->id,
            'assignee_id' => $this->worker1->id,
            'status' => 'assigned',
            'priority' => 'urgent',
            'created_at' => now()->subHours(5),
        ]);

        $assignedUrgentNewer = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->coordinator->id,
            'assignee_id' => $this->worker1->id,
            'status' => 'assigned',
            'priority' => 'urgent',
            'created_at' => now()->subHours(3),
        ]);

        $assignedHigh = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->coordinator->id,
            'assignee_id' => $this->worker1->id,
            'status' => 'assigned',
            'priority' => 'high',
            'created_at' => now()->subHours(2),
        ]);

        $assignedNormal = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->coordinator->id,
            'assignee_id' => $this->worker1->id,
            'status' => 'assigned',
            'priority' => 'normal',
            'created_at' => now()->subHours(4),
        ]);

        $doneUrgentOlder = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->coordinator->id,
            'assignee_id' => $this->worker1->id,
            'status' => 'done',
            'priority' => 'urgent',
            'created_at' => now()->subHours(8),
            'completed_at' => now()->subMinutes(20),
        ]);

        $doneLowNewer = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->coordinator->id,
            'assignee_id' => $this->worker1->id,
            'status' => 'done',
            'priority' => 'low',
            'created_at' => now()->subHours(9),
            'completed_at' => now()->subMinutes(5),
        ]);

        // 2. Direct Query Verification using PostIt::smartOrder()
        $orderedIds = PostIt::where('assignee_id', $this->worker1->id)
            ->smartOrder()
            ->pluck('id')
            ->all();

        $expectedDirectOrder = [
            $doingLow->id,            // Rank 1: 'doing' (even with 'low' priority)
            $assignedUrgentOlder->id, // Rank 2: 'assigned' + 'urgent' + older created_at (FIFO)
            $assignedUrgentNewer->id, // Rank 2: 'assigned' + 'urgent' + newer created_at
            $assignedHigh->id,        // Rank 2: 'assigned' + 'high'
            $assignedNormal->id,      // Rank 2: 'assigned' + 'normal'
            $doneLowNewer->id,        // Rank 4: 'done' + more recent completed_at
            $doneUrgentOlder->id,     // Rank 4: 'done' + older completed_at
        ];

        $this->assertSame($expectedDirectOrder, $orderedIds);

        // 3. In-memory Collection Sorting Verification via PostIt::sortCollection()
        $collectionSortedIds = PostIt::sortCollection(
            PostIt::where('assignee_id', $this->worker1->id)->get()
        )->pluck('id')->all();

        $this->assertSame($expectedDirectOrder, $collectionSortedIds);

        // 4. Livewire CapacityBoard Column Ordering Verification
        $livewire = Livewire::actingAs($this->worker1)
            ->test(CapacityBoard::class);

        $workers = $livewire->viewData('workers');
        $worker1Data = $workers->firstWhere('id', $this->worker1->id);
        $this->assertNotNull($worker1Data);

        // Active column contains 'doing' and 'assigned' cards in exact smart order
        $columnActiveCardIds = $worker1Data->assignedPostIts->pluck('id')->all();
        $expectedActiveOrder = [
            $doingLow->id,
            $assignedUrgentOlder->id,
            $assignedUrgentNewer->id,
            $assignedHigh->id,
            $assignedNormal->id,
        ];
        $this->assertSame($expectedActiveOrder, $columnActiveCardIds);

        // Done drawer query ordering (most recently completed first)
        $donePostIts = $livewire->viewData('donePostIts');
        $this->assertTrue($donePostIts->contains('id', $doneLowNewer->id));
        $this->assertTrue($donePostIts->contains('id', $doneUrgentOlder->id));
        $doneIds = $donePostIts->whereIn('id', [$doneLowNewer->id, $doneUrgentOlder->id])->pluck('id')->values()->all();
        $this->assertSame([$doneLowNewer->id, $doneUrgentOlder->id], $doneIds);

        // 5. Livewire Action Snap: Start working on $assignedUrgentOlder
        // When started, $assignedUrgentOlder becomes 'doing' and should rank above $doingLow due to higher priority ('urgent' vs 'low')
        $livewire->call('startPostIt', $assignedUrgentOlder->id);

        $updatedWorkers = $livewire->viewData('workers');
        $updatedWorker1 = $updatedWorkers->firstWhere('id', $this->worker1->id);
        $updatedActiveCardIds = $updatedWorker1->assignedPostIts->pluck('id')->all();

        $expectedUpdatedOrder = [
            $assignedUrgentOlder->id, // 'doing' + 'urgent'
            $doingLow->id,            // 'doing' + 'low'
            $assignedUrgentNewer->id, // 'assigned' + 'urgent'
            $assignedHigh->id,        // 'assigned' + 'high'
            $assignedNormal->id,      // 'assigned' + 'normal'
        ];
        $this->assertSame($expectedUpdatedOrder, $updatedActiveCardIds);

        // 6. Backlog Queue Smart Order Verification
        $backlogUrgentOlder = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->coordinator->id,
            'assignee_id' => null,
            'status' => 'backlog',
            'priority' => 'urgent',
            'created_at' => now()->subHours(6),
        ]);

        $backlogUrgentNewer = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->coordinator->id,
            'assignee_id' => null,
            'status' => 'backlog',
            'priority' => 'urgent',
            'created_at' => now()->subHours(4),
        ]);

        $backlogLow = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->coordinator->id,
            'assignee_id' => null,
            'status' => 'backlog',
            'priority' => 'low',
            'created_at' => now()->subHours(2),
        ]);

        $livewireBacklog = Livewire::actingAs($this->worker1)
            ->test(CapacityBoard::class);

        $backlogItems = $livewireBacklog->viewData('backlogPostIts');
        $backlogIds = $backlogItems->whereIn('id', [$backlogUrgentOlder->id, $backlogUrgentNewer->id, $backlogLow->id])
            ->pluck('id')
            ->values()
            ->all();

        $this->assertSame([$backlogUrgentOlder->id, $backlogUrgentNewer->id, $backlogLow->id], $backlogIds);
    }
}
