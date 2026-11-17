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

class VisibilityAndFirewallTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Department $department;

    protected User $admin;

    protected User $coordinator;

    protected User $publicWorker;

    protected User $awayWorker;

    protected User $internalWorker;

    protected User $privateWorkerA;

    protected User $privateWorkerB;

    protected User $clientA;

    protected User $clientB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['name' => 'Tantou Visibility Corp']);
        $this->department = Department::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Core Operations',
            'color' => '#3b82f6',
        ]);

        $this->admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Dimitris Admin',
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        $this->coordinator = User::factory()->coordinator()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Eleni Coordinator',
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        $this->publicWorker = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Nikos Public',
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        $this->awayWorker = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Kostas Away',
            'board_visibility' => 'public',
            'is_available' => false,
        ]);

        $this->internalWorker = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Maria Internal',
            'board_visibility' => 'internal',
            'is_available' => true,
        ]);

        $this->privateWorkerA = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Spyros Private A',
            'board_visibility' => 'private',
            'is_available' => true,
        ]);

        $this->privateWorkerB = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Anna Private B',
            'board_visibility' => 'private',
            'is_available' => true,
        ]);

        $this->clientA = User::factory()->client()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => null,
            'name' => 'Client Alice',
            'board_visibility' => 'none',
            'is_available' => true,
        ]);

        $this->clientB = User::factory()->client()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => null,
            'name' => 'Client Bob',
            'board_visibility' => 'none',
            'is_available' => true,
        ]);
    }

    /**
     * Verify that client users only see 'public' columns where is_available === true
     * (and never see 'internal', 'private', or Away columns in board or selector).
     */
    public function test_client_users_only_see_available_public_columns_and_never_internal_private_or_away_columns(): void
    {
        Livewire::actingAs($this->clientA)
            ->test(CapacityBoard::class)
            ->assertViewHas('workers', function ($workers) {
                $workerIds = $workers->pluck('id')->all();

                // Must see available public workers
                $hasPublicWorker = in_array($this->publicWorker->id, $workerIds, true);
                $hasAdmin = in_array($this->admin->id, $workerIds, true);
                $hasCoordinator = in_array($this->coordinator->id, $workerIds, true);

                // Must NOT see away worker
                $hasAwayWorker = in_array($this->awayWorker->id, $workerIds, true);

                // Must NOT see internal or private workers
                $hasInternalWorker = in_array($this->internalWorker->id, $workerIds, true);
                $hasPrivateWorker = in_array($this->privateWorkerA->id, $workerIds, true);

                return $hasPublicWorker && $hasAdmin && $hasCoordinator
                    && ! $hasAwayWorker && ! $hasInternalWorker && ! $hasPrivateWorker;
            })
            ->assertViewHas('availableCollaborators', function ($collabs) {
                $collabIds = $collabs->pluck('id')->all();

                // Selector must only offer available public collaborators to clients
                return in_array($this->publicWorker->id, $collabIds, true)
                    && ! in_array($this->awayWorker->id, $collabIds, true)
                    && ! in_array($this->internalWorker->id, $collabIds, true)
                    && ! in_array($this->privateWorkerA->id, $collabIds, true);
            });

        // Conversely, internal staff (coordinator) DOES see away worker (dimmed with AWAY badge) and internal worker
        Livewire::actingAs($this->coordinator)
            ->test(CapacityBoard::class)
            ->assertViewHas('workers', function ($workers) {
                $workerIds = $workers->pluck('id')->all();

                return in_array($this->awayWorker->id, $workerIds, true)
                    && in_array($this->internalWorker->id, $workerIds, true);
            })
            ->assertSee('AWAY');
    }

    /**
     * Verify that 'private' columns are strictly visible only to their owner.
     */
    public function test_private_columns_are_only_visible_to_their_owner(): void
    {
        // 1. Private Worker A sees their own column, but NOT Private Worker B's column
        Livewire::actingAs($this->privateWorkerA)
            ->test(CapacityBoard::class)
            ->assertViewHas('workers', function ($workers) {
                $workerIds = $workers->pluck('id')->all();

                return in_array($this->privateWorkerA->id, $workerIds, true)
                    && ! in_array($this->privateWorkerB->id, $workerIds, true);
            })
            ->assertSee('Spyros Private A')
            ->assertDontSee('Anna Private B');

        // 2. Private Worker B sees their own column, but NOT Private Worker A's column
        Livewire::actingAs($this->privateWorkerB)
            ->test(CapacityBoard::class)
            ->assertViewHas('workers', function ($workers) {
                $workerIds = $workers->pluck('id')->all();

                return in_array($this->privateWorkerB->id, $workerIds, true)
                    && ! in_array($this->privateWorkerA->id, $workerIds, true);
            })
            ->assertSee('Anna Private B')
            ->assertDontSee('Spyros Private A');

        // 3. Admin does NOT see private columns unless they own them
        Livewire::actingAs($this->admin)
            ->test(CapacityBoard::class)
            ->assertViewHas('workers', function ($workers) {
                $workerIds = $workers->pluck('id')->all();

                return ! in_array($this->privateWorkerA->id, $workerIds, true)
                    && ! in_array($this->privateWorkerB->id, $workerIds, true);
            });
    }

    /**
     * Verify the firewall rule: a PostIt created by a client cannot be assigned or moved
     * to an 'internal' or 'private' collaborator column, even by an admin.
     */
    public function test_firewall_blocks_client_originated_post_it_from_internal_or_private_columns(): void
    {
        // 1. Client user cannot create a card assigned to internal or private worker
        Livewire::actingAs($this->clientA)
            ->test(CapacityBoard::class)
            ->set('title', 'Client Illegal Assignment')
            ->set('priority', 'normal')
            ->set('weight', 2)
            ->set('assigneeId', $this->internalWorker->id)
            ->call('createPostIt')
            ->assertHasErrors(['assigneeId']);

        // 2. Client-originated backlog post-it
        $clientPostIt = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->clientA->id,
            'assignee_id' => null,
            'status' => 'backlog',
            'weight' => 2,
        ]);

        // 3. Admin attempts to move clientPostIt to internalWorker -> BLOCKED by firewall
        Livewire::actingAs($this->admin)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $clientPostIt->id, $this->internalWorker->id, 'assigned');

        $this->assertDatabaseHas('post_its', [
            'id' => $clientPostIt->id,
            'status' => 'backlog',
            'assignee_id' => null,
        ]);

        // 4. Admin attempts to move clientPostIt to privateWorkerA -> BLOCKED by firewall
        Livewire::actingAs($this->admin)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $clientPostIt->id, $this->privateWorkerA->id, 'assigned');

        $this->assertDatabaseHas('post_its', [
            'id' => $clientPostIt->id,
            'status' => 'backlog',
            'assignee_id' => null,
        ]);

        // 5. Coordinator attempts to move clientPostIt to internalWorker -> BLOCKED by firewall
        Livewire::actingAs($this->coordinator)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $clientPostIt->id, $this->internalWorker->id, 'assigned');

        $this->assertDatabaseHas('post_its', [
            'id' => $clientPostIt->id,
            'status' => 'backlog',
            'assignee_id' => null,
        ]);

        // 6. Moving clientPostIt to a public worker SUCCEEDS
        Livewire::actingAs($this->coordinator)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $clientPostIt->id, $this->publicWorker->id, 'assigned');

        $this->assertDatabaseHas('post_its', [
            'id' => $clientPostIt->id,
            'status' => 'assigned',
            'assignee_id' => $this->publicWorker->id,
        ]);

        // 7. Conversely, an internal task (originated by coordinator) CAN enter internal column
        $internalPostIt = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->coordinator->id,
            'assignee_id' => null,
            'status' => 'backlog',
            'weight' => 2,
        ]);

        Livewire::actingAs($this->admin)
            ->test(CapacityBoard::class)
            ->call('movePostIt', $internalPostIt->id, $this->internalWorker->id, 'assigned');

        $this->assertDatabaseHas('post_its', [
            'id' => $internalPostIt->id,
            'status' => 'assigned',
            'assignee_id' => $this->internalWorker->id,
        ]);
    }

    /**
     * Verify card anonymization: non-admin users see "Anonymous Request" (or localized equivalent)
     * on other users' active cards and cannot see other users' completed (done) cards.
     */
    public function test_card_anonymization_and_done_column_privacy_matrix_for_non_admins(): void
    {
        // Active task created by Client Alice
        $aliceActiveTask = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->clientA->id,
            'assignee_id' => $this->publicWorker->id,
            'status' => 'doing',
            'title' => 'Alice Confidential Task',
            'description' => 'Secret client trade formula',
        ]);

        // Done task created by Client Alice
        $aliceDoneTask = PostIt::factory()->done($this->publicWorker)->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->clientA->id,
            'title' => 'Alice Finished Request',
        ]);

        // Done task created by Admin
        $adminDoneTask = PostIt::factory()->done($this->publicWorker)->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->admin->id,
            'title' => 'Internal Server Upgrade Done',
        ]);

        // 1. Client Bob views the board:
        // - Alice's active task is anonymized with description hidden
        // - Done column omits both Alice's done task and Admin's done task
        Livewire::actingAs($this->clientB)
            ->test(CapacityBoard::class)
            ->assertViewHas('workers', function ($workers) use ($aliceActiveTask) {
                $worker = $workers->firstWhere('id', $this->publicWorker->id);
                $card = $worker?->assignedPostIts->firstWhere('id', $aliceActiveTask->id);

                return $card
                    && $card->display_requester_name === __('Anonymous Request')
                    && $card->is_masked === true
                    && $card->description === null
                    && $card->is_own === false;
            })
            ->assertViewHas('donePostIts', function ($done) use ($aliceDoneTask, $adminDoneTask) {
                return ! $done->contains('id', $aliceDoneTask->id)
                    && ! $done->contains('id', $adminDoneTask->id);
            });

        // 2. Client Alice views the board:
        // - Sees her OWN active task with requester name and unmasked description
        // - Sees her OWN done task in done column, but NOT Admin's done task
        Livewire::actingAs($this->clientA)
            ->test(CapacityBoard::class)
            ->assertViewHas('workers', function ($workers) use ($aliceActiveTask) {
                $worker = $workers->firstWhere('id', $this->publicWorker->id);
                $card = $worker?->assignedPostIts->firstWhere('id', $aliceActiveTask->id);

                return $card
                    && $card->display_requester_name === 'Client Alice'
                    && $card->is_masked === false
                    && $card->description === 'Secret client trade formula'
                    && $card->is_own === true;
            })
            ->assertViewHas('donePostIts', function ($done) use ($aliceDoneTask, $adminDoneTask) {
                return $done->contains('id', $aliceDoneTask->id)
                    && ! $done->contains('id', $adminDoneTask->id);
            });

        // 3. Admin views the board:
        // - Sees Alice's full identity and unmasked description
        // - Sees ALL completed tasks in the done column
        Livewire::actingAs($this->admin)
            ->test(CapacityBoard::class)
            ->assertViewHas('workers', function ($workers) use ($aliceActiveTask) {
                $worker = $workers->firstWhere('id', $this->publicWorker->id);
                $card = $worker?->assignedPostIts->firstWhere('id', $aliceActiveTask->id);

                return $card
                    && $card->display_requester_name === 'Client Alice'
                    && $card->is_masked === false
                    && $card->description === 'Secret client trade formula';
            })
            ->assertViewHas('donePostIts', function ($done) use ($aliceDoneTask, $adminDoneTask) {
                return $done->contains('id', $aliceDoneTask->id)
                    && $done->contains('id', $adminDoneTask->id);
            });
    }

    /**
     * Verify that column visibility mode changes are strictly restricted to Admin
     * and same-department Coordinator.
     */
    public function test_column_visibility_changes_restricted_strictly_to_admin_and_department_coordinator(): void
    {
        // 1. A collaborator (worker) attempting to change their own column_visibility is forbidden (403)
        // and their visibility remains unchanged.
        Livewire::actingAs($this->publicWorker)
            ->test(CapacityBoard::class)
            ->call('updateBoardVisibility', $this->publicWorker->id, 'private')
            ->assertForbidden();

        $this->assertSame('public', $this->publicWorker->fresh()->board_visibility);

        // When the collaborator views the board, they see the static non-clickable badge,
        // and the interactive wire:change dropdown for their column is NOT rendered.
        Livewire::actingAs($this->publicWorker)
            ->test(CapacityBoard::class)
            ->assertDontSee('wire:change="updateBoardVisibility('.$this->publicWorker->id);

        // 2. A client attempting to change column visibility is also forbidden (403)
        Livewire::actingAs($this->clientA)
            ->test(CapacityBoard::class)
            ->call('updateBoardVisibility', $this->publicWorker->id, 'internal')
            ->assertForbidden();

        $this->assertSame('public', $this->publicWorker->fresh()->board_visibility);

        // 3. An Admin can successfully change any collaborator's column_visibility
        Livewire::actingAs($this->admin)
            ->test(CapacityBoard::class)
            ->call('updateBoardVisibility', $this->publicWorker->id, 'internal')
            ->assertHasNoErrors();

        $this->assertSame('internal', $this->publicWorker->fresh()->board_visibility);

        // 4. A same-department coordinator can successfully change a collaborator's column_visibility
        Livewire::actingAs($this->coordinator)
            ->test(CapacityBoard::class)
            ->call('updateBoardVisibility', $this->publicWorker->id, 'public')
            ->assertHasNoErrors();

        $this->assertSame('public', $this->publicWorker->fresh()->board_visibility);

        // 5. Create Department B and a Coordinator for Department B
        $departmentB = Department::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Secondary Logistics',
            'color' => '#10b981',
        ]);

        $coordinatorB = User::factory()->coordinator()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $departmentB->id,
            'name' => 'Coordinator Dept B',
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        $workerB = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $departmentB->id,
            'name' => 'Worker Dept B',
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        // Coordinator from Department B CANNOT change the column_visibility of a collaborator in Department A
        Livewire::actingAs($coordinatorB)
            ->test(CapacityBoard::class)
            ->call('updateBoardVisibility', $this->publicWorker->id, 'private')
            ->assertForbidden();

        $this->assertSame('public', $this->publicWorker->fresh()->board_visibility);

        // Coordinator from Department A CANNOT change the column_visibility of a collaborator in Department B
        Livewire::actingAs($this->coordinator)
            ->test(CapacityBoard::class)
            ->call('updateBoardVisibility', $workerB->id, 'private')
            ->assertForbidden();

        $this->assertSame('public', $workerB->fresh()->board_visibility);

        // But Coordinator B CAN change collaborator in Department B
        Livewire::actingAs($coordinatorB)
            ->test(CapacityBoard::class)
            ->call('updateBoardVisibility', $workerB->id, 'internal')
            ->assertHasNoErrors();

        $this->assertSame('internal', $workerB->fresh()->board_visibility);
    }
}
