<?php

namespace Tests\Feature;

use App\Livewire\CapacityBoard;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Step18ContextualCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Edition::fake(false);
    }

    public function test_contextual_create_for_public_collaborator_prefills_department_and_assignee(): void
    {
        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $worker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        $authUser = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
        ]);

        $this->actingAs($authUser);

        Livewire::test(CapacityBoard::class)
            ->call('openCreateModal', $worker->id)
            ->assertSet('showCreateModal', true)
            ->assertSet('departmentId', $dept->id)
            ->assertSet('assigneeId', $worker->id);
    }

    public function test_contextual_create_for_backlog_forces_backlog_with_null_assignee(): void
    {
        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $worker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
        ]);

        $this->actingAs($worker);

        Livewire::test(CapacityBoard::class)
            ->call('openCreateModal', null, true)
            ->assertSet('showCreateModal', true)
            ->assertSet('assigneeId', null)
            ->assertSet('departmentId', $dept->id);

        Livewire::test(CapacityBoard::class)
            ->call('openCreateForBacklog')
            ->assertSet('showCreateModal', true)
            ->assertSet('assigneeId', null)
            ->assertSet('departmentId', $dept->id);
    }

    public function test_client_cannot_prefill_contextual_create_for_internal_collaborator(): void
    {
        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $internalWorker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'board_visibility' => 'internal',
            'is_available' => true,
        ]);

        $client = User::factory()->client()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($client);

        Livewire::test(CapacityBoard::class)
            ->call('openCreateModal', $internalWorker->id)
            ->assertSet('showCreateModal', true)
            ->assertSet('assigneeId', null);
    }

    public function test_client_cannot_prefill_contextual_create_for_private_collaborator(): void
    {
        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $privateWorker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'board_visibility' => 'private',
            'is_available' => true,
        ]);

        $client = User::factory()->client()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($client);

        Livewire::test(CapacityBoard::class)
            ->call('openCreateModal', $privateWorker->id)
            ->assertSet('showCreateModal', true)
            ->assertSet('assigneeId', null);
    }

    public function test_cannot_prefill_contextual_create_for_away_collaborator(): void
    {
        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $awayWorker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'board_visibility' => 'public',
            'is_available' => false,
        ]);

        $worker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
        ]);

        $this->actingAs($worker);

        Livewire::test(CapacityBoard::class)
            ->call('openCreateModal', $awayWorker->id)
            ->assertSet('showCreateModal', true)
            ->assertSet('assigneeId', null);
    }

    public function test_collaborator_column_renders_large_queue_slot_ghost_card(): void
    {
        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $worker = User::factory()->worker()->create([
            'name' => 'John Doe',
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        $this->actingAs($worker);

        Livewire::test(CapacityBoard::class)
            ->assertSeeHtml('wire:click="openCreateModal('.$worker->id.')"')
            ->assertSeeHtml(__('Queue Task Card'));
    }

    public function test_away_collaborator_column_does_not_render_queue_ghost_card(): void
    {
        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $awayWorker = User::factory()->worker()->create([
            'name' => 'Jane Away',
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'board_visibility' => 'public',
            'is_available' => false,
        ]);

        $worker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
        ]);

        $this->actingAs($worker);

        Livewire::test(CapacityBoard::class)
            ->assertSeeHtml(__('Collaborator is away'))
            ->assertDontSeeHtml('wire:click="openCreateModal('.$awayWorker->id.')"');
    }

    public function test_unassigned_backlog_column_renders_queue_slot_actions(): void
    {
        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $worker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
        ]);

        $this->actingAs($worker);

        // When backlog is empty, the empty-state box itself is the queue action
        Livewire::test(CapacityBoard::class)
            ->assertSeeHtml('wire:click="openCreateModal(null, true)"')
            ->assertSeeHtml(__('Click to queue an unassigned request'));
    }
}
