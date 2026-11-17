<?php

namespace Tests\Feature;

use App\Events\BoardUpdated;
use App\Livewire\CapacityBoard;
use App\Livewire\TeamManagement;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class Step36UserVisibilitySyncTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Department $department;

    protected User $admin;

    protected User $coordinator;

    protected User $worker;

    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['name' => 'Visibility Sync Corp']);
        $this->department = Department::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Engineering',
            'color' => '#6366f1',
        ]);

        $this->admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Admin Alex',
            'email' => 'admin@sync.corp',
            'visibility' => 'public',
            'is_available' => true,
        ]);

        $this->coordinator = User::factory()->coordinator()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Coord Chris',
            'email' => 'coord@sync.corp',
            'visibility' => 'public',
            'is_available' => true,
        ]);

        $this->worker = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Worker Walter',
            'email' => 'walter@sync.corp',
            'visibility' => 'public',
            'is_available' => true,
        ]);

        $this->client = User::factory()->client()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => null,
            'name' => 'Client Clara',
            'email' => 'clara@client.corp',
            'is_available' => true,
        ]);
    }

    public function test_user_model_has_visibility_column_and_fillable_defaults(): void
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Default User',
            'email' => 'testdefault@sync.corp',
            'password' => 'secret1234',
            'role' => 'worker',
        ]);

        $this->assertSame('public', $user->fresh()->visibility);
        $this->assertSame('public', $user->fresh()->board_visibility);

        // Update via visibility
        $user->update(['visibility' => 'internal']);
        $this->assertSame('internal', $user->fresh()->visibility);
        $this->assertSame('internal', $user->fresh()->board_visibility);

        // Update via board_visibility
        $user->update(['board_visibility' => 'private']);
        $this->assertSame('private', $user->fresh()->visibility);
        $this->assertSame('private', $user->fresh()->board_visibility);
    }

    public function test_updating_user_visibility_via_team_roster_modal_persists_to_database(): void
    {
        Event::fake([BoardUpdated::class]);

        $this->actingAs($this->admin);

        // 1. Open edit modal for Walter
        $component = Livewire::test(TeamManagement::class)
            ->call('openEditUserModal', $this->worker->id)
            ->assertSet('editingUserId', $this->worker->id)
            ->assertSet('userVisibility', 'public');

        // 2. Change visibility to 'internal' and save
        $component->set('userVisibility', 'internal')
            ->call('saveUser')
            ->assertHasNoErrors();

        $this->assertSame('internal', $this->worker->fresh()->visibility);
        $this->assertSame('internal', $this->worker->fresh()->board_visibility);

        // 3. Create a new member with 'private' visibility
        Livewire::test(TeamManagement::class)
            ->call('openCreateUserModal')
            ->set('userName', 'Private Pete')
            ->set('userEmail', 'pete@sync.corp')
            ->set('userPassword', 'Secret!2345678')
            ->set('userRole', 'worker')
            ->set('userDepartmentId', $this->department->id)
            ->set('userMaxCapacity', 8)
            ->set('userVisibility', 'private')
            ->call('saveUser')
            ->assertHasNoErrors();

        $pete = User::where('email', 'pete@sync.corp')->firstOrFail();
        $this->assertSame('private', $pete->visibility);
        $this->assertSame('private', $pete->board_visibility);
    }

    public function test_updating_user_visibility_via_team_roster_quick_dropdown_persists_to_database(): void
    {
        Event::fake([BoardUpdated::class]);

        $this->actingAs($this->admin);

        Livewire::test(TeamManagement::class)
            ->call('quickUpdateVisibility', $this->worker->id, 'internal')
            ->assertHasNoErrors();

        $this->assertSame('internal', $this->worker->fresh()->visibility);
        $this->assertSame('internal', $this->worker->fresh()->board_visibility);

        Livewire::test(TeamManagement::class)
            ->call('quickUpdateVisibility', $this->worker->id, 'none')
            ->assertHasNoErrors();

        $this->assertSame('none', $this->worker->fresh()->visibility);
        $this->assertSame('none', $this->worker->fresh()->board_visibility);
    }

    public function test_updating_user_visibility_directly_on_board_column_header_persists_to_database(): void
    {
        Event::fake([BoardUpdated::class]);

        $this->actingAs($this->admin);

        // Update using updateVisibility
        Livewire::test(CapacityBoard::class)
            ->call('updateVisibility', $this->worker->id, 'internal')
            ->assertHasNoErrors();

        $this->assertSame('internal', $this->worker->fresh()->visibility);
        $this->assertSame('internal', $this->worker->fresh()->board_visibility);

        // Update using updateBoardVisibility
        Livewire::test(CapacityBoard::class)
            ->call('updateBoardVisibility', $this->worker->id, 'private')
            ->assertHasNoErrors();

        $this->assertSame('private', $this->worker->fresh()->visibility);
        $this->assertSame('private', $this->worker->fresh()->board_visibility);
    }

    public function test_user_visibility_remains_stable_and_is_not_overwritten_by_post_it_assignments(): void
    {
        // 1. Worker is set to internal visibility
        $this->worker->update(['visibility' => 'internal']);
        $this->assertSame('internal', $this->worker->fresh()->visibility);

        $this->actingAs($this->admin);

        // 2. Create and assign a post-it to the worker
        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->admin->id,
            'assignee_id' => null,
            'title' => 'Internal System Maintenance',
            'status' => 'backlog',
            'weight' => 3,
            'priority' => 'high',
        ]);

        // 3. Move post-it to the worker via movePostIt
        Livewire::test(CapacityBoard::class)
            ->call('movePostIt', $postIt->id, $this->worker->id, 'assigned')
            ->assertHasNoErrors();

        // 4. Verify post-it is assigned and capacity updated
        $this->assertSame('assigned', $postIt->fresh()->status);
        $this->assertSame($this->worker->id, $postIt->fresh()->assignee_id);
        $this->assertSame(3, $this->worker->fresh()->current_capacity);

        // 5. CRITICAL: Worker's visibility MUST remain 'internal' and not overwritten
        $this->assertSame('internal', $this->worker->fresh()->visibility);
        $this->assertSame('internal', $this->worker->fresh()->board_visibility);

        // 6. Start the post-it
        Livewire::test(CapacityBoard::class)
            ->call('movePostIt', $postIt->id, $this->worker->id, 'doing')
            ->assertHasNoErrors();

        $this->assertSame('internal', $this->worker->fresh()->visibility);

        // 7. Complete the post-it
        Livewire::test(CapacityBoard::class)
            ->call('movePostIt', $postIt->id, $this->worker->id, 'done')
            ->assertHasNoErrors();

        $this->assertSame('internal', $this->worker->fresh()->visibility);
    }

    public function test_user_visibility_acts_as_single_source_of_truth_for_board_rendering(): void
    {
        // 1. Walter is initially public
        $this->worker->update(['visibility' => 'public']);

        // Client views board: Walter's column is visible
        Livewire::actingAs($this->client)
            ->test(CapacityBoard::class)
            ->assertViewHas('workers', function ($workers) {
                return $workers->contains('id', $this->worker->id);
            });

        // 2. Admin switches Walter to 'internal'
        $this->actingAs($this->admin);
        Livewire::test(CapacityBoard::class)
            ->call('updateVisibility', $this->worker->id, 'internal')
            ->assertHasNoErrors();

        $this->assertSame('internal', $this->worker->fresh()->visibility);

        // Client views board again: Walter's column is now hidden
        Livewire::actingAs($this->client)
            ->test(CapacityBoard::class)
            ->assertViewHas('workers', function ($workers) {
                return ! $workers->contains('id', $this->worker->id);
            });

        // Admin views board: Walter's column is visible
        Livewire::actingAs($this->admin)
            ->test(CapacityBoard::class)
            ->assertViewHas('workers', function ($workers) {
                return $workers->contains('id', $this->worker->id);
            });

        // 3. Admin switches Walter back to 'public'
        Livewire::actingAs($this->admin)
            ->test(CapacityBoard::class)
            ->call('updateVisibility', $this->worker->id, 'public')
            ->assertHasNoErrors();

        $this->assertSame('public', $this->worker->fresh()->visibility);

        // Client views board again: Walter's column is visible again
        Livewire::actingAs($this->client)
            ->test(CapacityBoard::class)
            ->assertViewHas('workers', function ($workers) {
                return $workers->contains('id', $this->worker->id);
            });
    }
}
