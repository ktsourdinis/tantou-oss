<?php

namespace Tests\Feature;

use App\Events\BoardUpdated;
use App\Livewire\CapacityBoard;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class Step17SmartDefaultsAndModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_collaborator_gets_smart_default_department_and_self_assignee(): void
    {
        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $collaborator = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
        ]);

        $this->actingAs($collaborator);

        Livewire::test(CapacityBoard::class)
            ->assertSet('departmentId', $dept->id)
            ->assertSet('assigneeId', $collaborator->id)
            ->call('openCreateModal')
            ->assertSet('showCreateModal', true)
            ->assertSet('departmentId', $dept->id)
            ->assertSet('assigneeId', $collaborator->id)
            ->assertSee(__('Assigned To'));
    }

    public function test_coordinator_gets_smart_default_department_and_null_assignee(): void
    {
        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $coordinator = User::factory()->coordinator()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
        ]);

        $this->actingAs($coordinator);

        Livewire::test(CapacityBoard::class)
            ->assertSet('departmentId', $dept->id)
            ->assertSet('assigneeId', null)
            ->call('openCreateModal')
            ->assertSet('showCreateModal', true)
            ->assertSet('departmentId', $dept->id)
            ->assertSet('assigneeId', null);
    }

    public function test_changing_department_clears_mismatched_assignee(): void
    {
        $tenant = Tenant::factory()->create();
        $dept1 = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Engineering']);
        $dept2 = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Marketing']);

        $worker1 = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept1->id,
        ]);

        $this->actingAs($worker1);

        Livewire::test(CapacityBoard::class)
            ->assertSet('departmentId', $dept1->id)
            ->assertSet('assigneeId', $worker1->id)
            ->set('departmentId', $dept2->id)
            ->assertSet('assigneeId', null);
    }

    public function test_authorized_user_can_edit_post_it_details_in_centered_modal(): void
    {
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $worker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
        ]);

        $postIt = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'requester_id' => $worker->id,
            'assignee_id' => $worker->id,
            'title' => 'Original Title',
            'description' => 'Original Description',
            'priority' => 'normal',
            'weight' => 2,
            'status' => 'assigned',
        ]);

        $this->actingAs($worker);

        Livewire::test(CapacityBoard::class)
            ->call('openSlideOver', $postIt->id)
            ->assertSet('showSlideOver', true)
            ->assertSet('isEditingDetail', false)
            ->assertSet('editTitle', 'Original Title')
            ->call('startEditingDetail')
            ->assertSet('isEditingDetail', true)
            ->set('editTitle', 'Updated New Title')
            ->set('editDescription', 'Updated New Description')
            ->set('editPriority', 'urgent')
            ->set('editWeight', 4)
            ->call('savePostItEdits')
            ->assertSet('isEditingDetail', false);

        $postIt->refresh();
        $this->assertEquals('Updated New Title', $postIt->title);
        $this->assertEquals('Updated New Description', $postIt->description);
        $this->assertEquals('urgent', $postIt->priority);
        $this->assertEquals(4, $postIt->weight);
    }

    public function test_quick_action_start_and_complete_from_detail_modal(): void
    {
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create();
        $worker = User::factory()->worker()->create(['tenant_id' => $tenant->id]);

        $postIt = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $worker->id,
            'assignee_id' => $worker->id,
            'status' => 'assigned',
            'started_at' => null,
            'completed_at' => null,
        ]);

        $this->actingAs($worker);

        // Start from modal
        Livewire::test(CapacityBoard::class)
            ->call('openSlideOver', $postIt->id)
            ->call('startPostIt', $postIt->id);

        $postIt->refresh();
        $this->assertEquals('doing', $postIt->status);
        $this->assertNotNull($postIt->started_at);

        // Complete from modal
        Livewire::test(CapacityBoard::class)
            ->call('openSlideOver', $postIt->id)
            ->call('completePostIt', $postIt->id);

        $postIt->refresh();
        $this->assertEquals('done', $postIt->status);
        $this->assertNotNull($postIt->completed_at);
    }

    public function test_firewall_blocks_client_post_it_transfer_to_internal_column_in_edit(): void
    {
        $tenant = Tenant::factory()->create();
        $client = User::factory()->client()->create(['tenant_id' => $tenant->id]);
        $publicWorker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'board_visibility' => 'public',
            'is_available' => true,
        ]);
        $internalWorker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'board_visibility' => 'internal',
            'is_available' => true,
        ]);

        $postIt = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client->id,
            'assignee_id' => $publicWorker->id,
            'status' => 'assigned',
        ]);

        $this->actingAs($client);

        Livewire::test(CapacityBoard::class)
            ->call('openSlideOver', $postIt->id)
            ->call('startEditingDetail')
            ->set('editAssigneeId', $internalWorker->id)
            ->call('savePostItEdits')
            ->assertHasErrors(['editAssigneeId']);

        $postIt->refresh();
        $this->assertEquals($publicWorker->id, $postIt->assignee_id);
    }
}
