<?php

namespace Tests\Feature;

use App\Livewire\CapacityBoard;
use App\Livewire\TeamManagement;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Step9BoardModesAndAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test column visibility modes: clients only see public columns,
     * staff sees public + internal + own private column.
     */
    public function test_column_visibility_modes_for_client_and_internal_staff(): void
    {
        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);

        $client = User::factory()->client()->create([
            'tenant_id' => $tenant->id,
            'board_visibility' => 'none',
        ]);

        $publicWorker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'name' => 'Public Worker',
            'board_visibility' => 'public',
        ]);

        $internalWorker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'name' => 'Internal Worker',
            'board_visibility' => 'internal',
        ]);

        $privateWorker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'name' => 'Private Worker',
            'board_visibility' => 'private',
        ]);

        // 1. Client viewer ONLY sees public columns
        $this->actingAs($client);
        Livewire::test(CapacityBoard::class)
            ->assertSee('Public Worker')
            ->assertDontSee('Internal Worker')
            ->assertDontSee('Private Worker');

        // 2. Internal worker viewer sees public + internal, but NOT other users' private columns
        $this->actingAs($publicWorker);
        Livewire::test(CapacityBoard::class)
            ->assertSee('Public Worker')
            ->assertSee('Internal Worker')
            ->assertDontSee('Private Worker');

        // 3. Private column owner sees their OWN private column in addition to public + internal
        $this->actingAs($privateWorker);
        Livewire::test(CapacityBoard::class)
            ->assertSee('Public Worker')
            ->assertSee('Internal Worker')
            ->assertSee('Private Worker');
    }

    /**
     * Test dual-lens Department Radar: clients see only public available capacity,
     * internal staff sees public + internal available capacity, away staff excluded.
     */
    public function test_dual_lens_department_radar_calculation(): void
    {
        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);

        $client = User::factory()->client()->create(['tenant_id' => $tenant->id]);

        $publicWorker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'board_visibility' => 'public',
            'is_available' => true,
            'current_capacity' => 2,
            'max_capacity' => 10,
        ]);

        $internalWorker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'board_visibility' => 'internal',
            'is_available' => true,
            'current_capacity' => 4,
            'max_capacity' => 10,
        ]);

        $awayWorker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'board_visibility' => 'public',
            'is_available' => false, // Away!
            'current_capacity' => 3,
            'max_capacity' => 10,
        ]);

        // Client radar view: only public worker capacity (max = 10, current = 2)
        $this->actingAs($client);
        $clientBoard = Livewire::test(CapacityBoard::class);
        $clientTabs = $clientBoard->viewData('radarTabs');
        $deptTabClient = collect($clientTabs)->firstWhere('id', $dept->id);
        $this->assertEquals(10, $deptTabClient['max_capacity']);
        $this->assertEquals(2, $deptTabClient['current_capacity']);

        // Internal staff radar view: public + internal (max = 20, current = 6), away worker excluded
        $this->actingAs($publicWorker);
        $staffBoard = Livewire::test(CapacityBoard::class);
        $staffTabs = $staffBoard->viewData('radarTabs');
        $deptTabStaff = collect($staffTabs)->firstWhere('id', $dept->id);
        $this->assertEquals(20, $deptTabStaff['max_capacity']);
        $this->assertEquals(6, $deptTabStaff['current_capacity']);
    }

    /**
     * Test availability toggle and away column blocking client drops.
     */
    public function test_availability_toggle_and_client_assignment_guard(): void
    {
        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);

        $client = User::factory()->client()->create(['tenant_id' => $tenant->id]);
        $worker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        // Worker toggles availability to away
        $this->actingAs($worker);
        Livewire::test(CapacityBoard::class)
            ->call('toggleAvailability', $worker->id);

        $this->assertFalse($worker->fresh()->is_available);

        // Client tries to create Post-it directly assigned to away worker -> should be blocked
        $this->actingAs($client);
        Livewire::test(CapacityBoard::class)
            ->set('title', 'Test Request')
            ->set('priority', 'normal')
            ->set('weight', 2)
            ->set('assigneeId', $worker->id)
            ->call('createPostIt')
            ->assertHasErrors(['assigneeId']);

        // Client tries to move unassigned card to away worker -> should be rejected
        $backlogPostIt = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client->id,
            'status' => 'backlog',
            'assignee_id' => null,
        ]);

        Livewire::test(CapacityBoard::class)
            ->call('movePostIt', $backlogPostIt->id, $worker->id, 'assigned');

        $this->assertNull($backlogPostIt->fresh()->assignee_id);
    }

    /**
     * Test two-stage active workflow: assigned vs doing, Start button, and capacity calculation.
     */
    public function test_two_stage_workflow_and_start_action(): void
    {
        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);

        $worker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'board_visibility' => 'public',
            'current_capacity' => 0,
            'max_capacity' => 10,
        ]);

        $this->actingAs($worker);

        // Create post-it directly assigned
        Livewire::test(CapacityBoard::class)
            ->set('title', 'Refactor billing')
            ->set('priority', 'normal')
            ->set('weight', 4)
            ->set('assigneeId', $worker->id)
            ->call('createPostIt');

        $postIt = PostIt::where('assignee_id', $worker->id)->firstOrFail();
        $this->assertEquals('assigned', $postIt->status);
        $this->assertNull($postIt->started_at);

        // Capacity counts assigned tasks
        $this->assertEquals(4, $worker->fresh()->current_capacity);

        // Worker clicks Start
        Livewire::test(CapacityBoard::class)
            ->call('startPostIt', $postIt->id);

        $this->assertEquals('doing', $postIt->fresh()->status);
        $this->assertNotNull($postIt->fresh()->started_at);

        // Capacity still counts doing tasks
        $this->assertEquals(4, $worker->fresh()->current_capacity);
    }

    /**
     * Test client can reassign unstarted cards ('assigned' or 'backlog') but is locked once 'doing'.
     */
    public function test_client_reassignment_rules_for_unstarted_and_doing_cards(): void
    {
        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);

        $client = User::factory()->client()->create(['tenant_id' => $tenant->id]);

        $worker1 = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        $worker2 = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        $this->actingAs($client);

        // 1. Client creates card assigned to worker1 (status = 'assigned', started_at = null)
        $unstartedPostIt = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client->id,
            'assignee_id' => $worker1->id,
            'status' => 'assigned',
            'started_at' => null,
            'weight' => 2,
        ]);

        // Client can reassign unstarted card to worker2
        Livewire::test(CapacityBoard::class)
            ->call('movePostIt', $unstartedPostIt->id, $worker2->id, 'assigned');

        $this->assertEquals($worker2->id, $unstartedPostIt->fresh()->assignee_id);

        // Client can move back to backlog
        Livewire::test(CapacityBoard::class)
            ->call('movePostIt', $unstartedPostIt->id, null, 'backlog');

        $this->assertEquals('backlog', $unstartedPostIt->fresh()->status);
        $this->assertNull($unstartedPostIt->fresh()->assignee_id);

        // 2. Card actively being worked on (status = 'doing', started_at != null)
        $activePostIt = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client->id,
            'assignee_id' => $worker1->id,
            'status' => 'doing',
            'started_at' => now()->subHour(),
            'weight' => 3,
        ]);

        // Client attempts to reassign active card -> should be rejected!
        Livewire::test(CapacityBoard::class)
            ->call('movePostIt', $activePostIt->id, $worker2->id, 'assigned');

        $this->assertEquals($worker1->id, $activePostIt->fresh()->assignee_id);
    }

    /**
     * Test coordinator permissions: can rebalance cards across collaborators within their department.
     */
    public function test_coordinator_can_rebalance_within_own_department(): void
    {
        $tenant = Tenant::factory()->create();
        $softwareDept = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Software']);
        $opsDept = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ops']);

        $coordinator = User::factory()->coordinator()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
        ]);

        $softwareWorker1 = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'board_visibility' => 'public',
        ]);

        $softwareWorker2 = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'board_visibility' => 'public',
        ]);

        $softwarePostIt = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'assignee_id' => $softwareWorker1->id,
            'status' => 'assigned',
            'weight' => 3,
        ]);

        // Coordinator rebalances card to softwareWorker2
        $this->actingAs($coordinator);
        Livewire::test(CapacityBoard::class)
            ->call('movePostIt', $softwarePostIt->id, $softwareWorker2->id, 'assigned');

        $this->assertEquals($softwareWorker2->id, $softwarePostIt->fresh()->assignee_id);
    }

    /**
     * Test Co-op rules: prevent self-helper, reset on backlog, enforce same-mode matching.
     */
    public function test_coop_rules_and_same_mode_matching(): void
    {
        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);

        $publicWorker1 = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'board_visibility' => 'public',
        ]);

        $publicWorker2 = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'board_visibility' => 'public',
        ]);

        $internalWorker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'board_visibility' => 'internal',
        ]);

        $postIt = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'assignee_id' => $publicWorker1->id,
            'status' => 'doing',
            'weight' => 3,
        ]);

        $this->actingAs($publicWorker1);

        // 1. Assignee CANNOT be their own helper
        Livewire::test(CapacityBoard::class)
            ->call('openCoOpModal', $postIt->id)
            ->set('coOpHelperId', $publicWorker1->id)
            ->set('coOpHelperSplit', 20)
            ->call('saveCoOpSplit');

        $this->assertNull($postIt->fresh()->helper_id);

        // 2. Public assignee CANNOT select internal helper (mode mismatch)
        Livewire::test(CapacityBoard::class)
            ->call('openCoOpModal', $postIt->id)
            ->set('coOpHelperId', $internalWorker->id)
            ->set('coOpHelperSplit', 25)
            ->call('saveCoOpSplit');

        $this->assertNull($postIt->fresh()->helper_id);

        // 3. Public assignee selects public helper -> valid!
        Livewire::test(CapacityBoard::class)
            ->call('openCoOpModal', $postIt->id)
            ->set('coOpHelperId', $publicWorker2->id)
            ->set('coOpHelperSplit', 30)
            ->call('saveCoOpSplit');

        $this->assertEquals($publicWorker2->id, $postIt->fresh()->helper_id);
        $this->assertEquals(30, $postIt->fresh()->helper_split);

        // 4. Moving to backlog automatically resets helper_id, helper_split, and started_at
        Livewire::test(CapacityBoard::class)
            ->call('movePostIt', $postIt->id, null, 'backlog');

        $fresh = $postIt->fresh();
        $this->assertEquals('backlog', $fresh->status);
        $this->assertNull($fresh->assignee_id);
        $this->assertNull($fresh->helper_id);
        $this->assertEquals(0, $fresh->helper_split);
        $this->assertNull($fresh->started_at);
    }

    /**
     * Test Team Management creates and edits user with coordinator role, board_visibility, and availability.
     */
    public function test_team_management_supports_coordinator_and_visibility(): void
    {
        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($admin);

        Livewire::test(TeamManagement::class)
            ->call('openCreateUserModal')
            ->set('userName', 'Team Lead Nikos')
            ->set('userEmail', 'lead@tantou.local')
            ->set('userPassword', 'secret123')
            ->set('userRole', 'coordinator')
            ->set('userDepartmentId', $dept->id)
            ->set('userMaxCapacity', 15)
            ->set('userBoardVisibility', 'public')
            ->set('userIsAvailable', true)
            ->call('saveUser');

        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->id,
            'name' => 'Team Lead Nikos',
            'role' => 'coordinator',
            'board_visibility' => 'public',
            'is_available' => true,
        ]);
    }
}
