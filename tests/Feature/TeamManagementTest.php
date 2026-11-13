<?php

namespace Tests\Feature;

use App\Events\BoardUpdated;
use App\Livewire\TeamManagement;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_team(): void
    {
        $response = $this->get(route('team'));
        $response->assertRedirect(route('login'));
    }

    public function test_client_is_redirected_away_from_team(): void
    {
        $tenant = Tenant::factory()->create();
        $client = User::factory()->client()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($client);
        $response = $this->get(route('team'));
        $response->assertRedirect(route('board'));
    }

    public function test_worker_and_admin_can_access_team(): void
    {
        $tenant = Tenant::factory()->create();
        $worker = User::factory()->worker()->create(['tenant_id' => $tenant->id]);
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($worker);
        $response = $this->get(route('team'));
        $response->assertOk();

        $this->actingAs($admin);
        $response = $this->get(route('team'));
        $response->assertOk();
    }

    public function test_admin_can_create_edit_and_delete_department(): void
    {
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create();
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($admin);

        // 1. Create Department
        Livewire::test(TeamManagement::class)
            ->call('openCreateDepartmentModal')
            ->set('departmentName', 'Security Ops')
            ->set('departmentSlug', 'security-ops')
            ->set('departmentColor', '#f43f5e')
            ->call('saveDepartment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('departments', [
            'tenant_id' => $tenant->id,
            'name' => 'Security Ops',
            'slug' => 'security-ops',
            'color' => '#f43f5e',
        ]);

        $dept = Department::where('slug', 'security-ops')->firstOrFail();

        // 2. Edit Department
        Livewire::test(TeamManagement::class)
            ->call('openEditDepartmentModal', $dept->id)
            ->set('departmentName', 'SecOps Advanced')
            ->set('departmentColor', '#8b5cf6')
            ->call('saveDepartment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('departments', [
            'id' => $dept->id,
            'name' => 'SecOps Advanced',
            'color' => '#8b5cf6',
        ]);

        // 3. Delete Department
        Livewire::test(TeamManagement::class)
            ->call('deleteDepartment', $dept->id);

        $this->assertDatabaseMissing('departments', [
            'id' => $dept->id,
        ]);

        Event::assertDispatched(BoardUpdated::class);
    }

    public function test_worker_cannot_manage_departments_or_users(): void
    {
        $tenant = Tenant::factory()->create();
        $worker = User::factory()->worker()->create(['tenant_id' => $tenant->id]);
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($worker);

        Livewire::test(TeamManagement::class)
            ->call('openCreateDepartmentModal')
            ->assertForbidden();

        Livewire::test(TeamManagement::class)
            ->call('deleteDepartment', $dept->id)
            ->assertForbidden();

        Livewire::test(TeamManagement::class)
            ->call('openCreateUserModal')
            ->assertForbidden();

        Livewire::test(TeamManagement::class)
            ->call('quickAdjustCapacity', $worker->id, 2)
            ->assertForbidden();

        Livewire::test(TeamManagement::class)
            ->call('deleteUser', $worker->id)
            ->assertForbidden();
    }

    public function test_admin_can_create_edit_and_delete_user(): void
    {
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create();
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($admin);

        // 1. Create User
        Livewire::test(TeamManagement::class)
            ->call('openCreateUserModal')
            ->set('userName', 'Alexandros T.')
            ->set('userEmail', 'alex@example.com')
            ->set('userPassword', 'secret123')
            ->set('userRole', 'worker')
            ->set('userDepartmentId', $dept->id)
            ->set('userMaxCapacity', 15)
            ->call('saveUser')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->id,
            'name' => 'Alexandros T.',
            'email' => 'alex@example.com',
            'role' => 'worker',
            'department_id' => $dept->id,
            'max_capacity' => 15,
        ]);

        $createdUser = User::where('email', 'alex@example.com')->firstOrFail();

        // 2. Edit User
        Livewire::test(TeamManagement::class)
            ->call('openEditUserModal', $createdUser->id)
            ->set('userName', 'Alex T. Senior')
            ->set('userMaxCapacity', 20)
            ->call('saveUser')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $createdUser->id,
            'name' => 'Alex T. Senior',
            'max_capacity' => 20,
        ]);

        // 3. Quick-adjust capacity
        Livewire::test(TeamManagement::class)
            ->call('quickAdjustCapacity', $createdUser->id, 5);

        $this->assertEquals(25, $createdUser->fresh()->max_capacity);

        // 4. Delete User
        Livewire::test(TeamManagement::class)
            ->call('deleteUser', $createdUser->id);

        $this->assertDatabaseMissing('users', [
            'id' => $createdUser->id,
        ]);

        // 5. Admin cannot delete self
        Livewire::test(TeamManagement::class)
            ->call('deleteUser', $admin->id)
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
        ]);
    }

    public function test_gamification_leaderboard_standings_and_coop_metrics(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $worker1 = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Worker Alpha',
            'total_points' => 350,
            'current_capacity' => 4,
            'max_capacity' => 10,
        ]);

        $worker2 = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Worker Beta',
            'total_points' => 500,
            'current_capacity' => 2,
            'max_capacity' => 10,
        ]);

        // 3 tasks completed by Worker2 as Tantou
        PostIt::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'assignee_id' => $worker2->id,
            'status' => 'done',
        ]);

        // 2 tasks completed where Worker1 was Co-op helper
        PostIt::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'assignee_id' => $worker2->id,
            'helper_id' => $worker1->id,
            'status' => 'done',
        ]);

        $this->actingAs($admin);

        Livewire::test(TeamManagement::class)
            ->assertSeeInOrder(['Worker Beta', 'Worker Alpha']) // 500 pts vs 350 pts
            ->assertSee('500 pts')
            ->assertSee('350 pts')
            ->assertSee('2') // Co-op count
            ->assertSee('3'); // Tantou count
    }
}
