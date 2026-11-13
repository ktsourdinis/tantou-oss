<?php

namespace Tests\Feature;

use App\Livewire\CapacityBoard;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BoardUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_radar_tabs_rendered_with_aggregate_capacity(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $deptSoftware = Department::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Software',
        ]);
        $deptInfra = Department::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Infrastructure',
        ]);

        $worker1 = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $deptSoftware->id,
            'current_capacity' => 6,
            'max_capacity' => 10,
        ]);

        $worker2 = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $deptInfra->id,
            'current_capacity' => 8,
            'max_capacity' => 10,
        ]);

        $this->actingAs($admin);

        Livewire::test(CapacityBoard::class)
            ->assertViewHas('radarTabs', function ($tabs) {
                // Should have 'All Departments', 'Software', 'Infrastructure'
                return count($tabs) === 3
                    && $tabs[0]['name'] === 'All Departments'
                    && $tabs[0]['current_capacity'] === 14
                    && $tabs[0]['max_capacity'] === 20;
            })
            ->call('selectDepartment', $deptSoftware->id)
            ->assertSet('selectedDepartmentId', $deptSoftware->id)
            ->assertViewHas('workers', function ($workers) use ($worker1, $worker2) {
                return $workers->contains('id', $worker1->id)
                    && ! $workers->contains('id', $worker2->id);
            });
    }

    public function test_direct_collaborator_selection_on_creation(): void
    {
        $tenant = Tenant::factory()->create();
        $client = User::factory()->client()->create(['tenant_id' => $tenant->id]);
        $worker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'current_capacity' => 0,
            'max_capacity' => 10,
        ]);

        $this->actingAs($client);

        Livewire::test(CapacityBoard::class)
            ->set('title', 'Directly Assigned Task')
            ->set('weight', 3)
            ->set('priority', 'urgent')
            ->set('assigneeId', $worker->id)
            ->call('createPostIt');

        $this->assertDatabaseHas('post_its', [
            'tenant_id' => $tenant->id,
            'title' => 'Directly Assigned Task',
            'status' => 'doing',
            'assignee_id' => $worker->id,
            'weight' => 3,
            'priority' => 'urgent',
        ]);

        // Worker capacity should be recalculated to 3
        $this->assertEquals(3, $worker->fresh()->current_capacity);
    }

    public function test_privacy_matrix_anonymizes_active_cards_for_non_admins(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
        $clientA = User::factory()->client()->create(['tenant_id' => $tenant->id, 'name' => 'Alice Client']);
        $clientB = User::factory()->client()->create(['tenant_id' => $tenant->id, 'name' => 'Bob Client']);

        $worker = User::factory()->worker()->create(['tenant_id' => $tenant->id]);

        // Task created by Alice
        $aliceTask = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $clientA->id,
            'assignee_id' => $worker->id,
            'status' => 'doing',
            'title' => 'Alice Secret Feature',
            'description' => 'Top secret client requirement',
        ]);

        // Task created by Bob
        $bobTask = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $clientB->id,
            'status' => 'backlog',
            'title' => 'Bob Public Task',
        ]);

        // 1. Bob logs in: Alice's task should have anonymized requester name and masked description
        $this->actingAs($clientB);

        Livewire::test(CapacityBoard::class)
            ->assertViewHas('workers', function ($workers) use ($aliceTask) {
                $postIt = $workers->first()->assignedPostIts->where('id', $aliceTask->id)->first();

                return $postIt
                    && $postIt->display_requester_name === 'Ανώνυμο Αίτημα'
                    && $postIt->is_masked === true
                    && $postIt->description === null
                    && $postIt->is_own === false;
            })
            ->assertViewHas('backlogPostIts', function ($backlog) use ($bobTask) {
                $postIt = $backlog->where('id', $bobTask->id)->first();

                return $postIt && $postIt->is_own === true;
            });

        // 2. Admin logs in: Admin sees full requester identity and description
        $this->actingAs($admin);

        Livewire::test(CapacityBoard::class)
            ->assertViewHas('workers', function ($workers) use ($aliceTask) {
                $postIt = $workers->first()->assignedPostIts->where('id', $aliceTask->id)->first();

                return $postIt
                    && $postIt->display_requester_name === 'Alice Client'
                    && $postIt->is_masked === false
                    && $postIt->description === 'Top secret client requirement';
            });
    }

    public function test_privacy_matrix_restricts_done_column_for_non_admins(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
        $worker1 = User::factory()->worker()->create(['tenant_id' => $tenant->id]);
        $worker2 = User::factory()->worker()->create(['tenant_id' => $tenant->id]);
        $client = User::factory()->client()->create(['tenant_id' => $tenant->id]);

        // Task 1: Completed by worker 1 (Client requested)
        $task1 = PostIt::factory()->done($worker1)->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client->id,
            'title' => 'Client Task Done',
        ]);

        // Task 2: Completed by worker 2 (Admin requested)
        $task2 = PostIt::factory()->done($worker2)->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $admin->id,
            'title' => 'Internal Admin Task Done',
        ]);

        // 1. Client only sees task1 (their own request), task2 is completely omitted
        $this->actingAs($client);
        Livewire::test(CapacityBoard::class)
            ->assertViewHas('donePostIts', function ($done) use ($task1, $task2) {
                return $done->contains('id', $task1->id)
                    && ! $done->contains('id', $task2->id);
            });

        // 2. Admin sees both completed tasks
        $this->actingAs($admin);
        Livewire::test(CapacityBoard::class)
            ->assertViewHas('donePostIts', function ($done) use ($task1, $task2) {
                return $done->contains('id', $task1->id)
                    && $done->contains('id', $task2->id);
            });
    }

    public function test_co_op_assist_assignment_and_points_split_on_done(): void
    {
        $tenant = Tenant::factory()->create();
        $assignee = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'total_points' => 0,
        ]);
        $helper = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'total_points' => 0,
        ]);

        $postIt = PostIt::factory()->doing($assignee)->create([
            'tenant_id' => $tenant->id,
            'bounty_points' => 40,
            'weight' => 2,
        ]);

        // Assignee opens modal and sets 30% co-op split
        $this->actingAs($assignee);

        Livewire::test(CapacityBoard::class)
            ->call('openCoOpModal', $postIt->id)
            ->assertSet('coOpPostItId', $postIt->id)
            ->set('coOpHelperId', $helper->id)
            ->set('coOpHelperSplit', 30)
            ->call('saveCoOpSplit');

        $this->assertDatabaseHas('post_its', [
            'id' => $postIt->id,
            'helper_id' => $helper->id,
            'helper_split' => 30,
        ]);

        // Move task to done
        Livewire::test(CapacityBoard::class)
            ->call('movePostIt', $postIt->id, $assignee->id, 'done');

        // Total 40 pts: Helper 30% = 12 pts, Assignee 70% = 28 pts
        $this->assertEquals(28, $assignee->fresh()->total_points);
        $this->assertEquals(12, $helper->fresh()->total_points);
    }
}
