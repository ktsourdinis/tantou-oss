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

class MultiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected User $adminA;

    protected User $workerA;

    protected User $adminB;

    protected User $workerB;

    protected Department $deptA;

    protected Department $deptB;

    protected PostIt $postItA;

    protected PostIt $postItB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['name' => 'Acme Greece', 'slug' => 'acme-greece']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Apex Global', 'slug' => 'apex-global']);

        $this->deptA = Department::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Dept Alpha',
            'slug' => 'dept-alpha',
        ]);
        $this->deptB = Department::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Dept Beta',
            'slug' => 'dept-beta',
        ]);

        $this->adminA = User::factory()->admin()->create([
            'tenant_id' => $this->tenantA->id,
            'department_id' => $this->deptA->id,
            'name' => 'Admin Alpha',
            'board_visibility' => 'public',
        ]);
        $this->workerA = User::factory()->worker()->create([
            'tenant_id' => $this->tenantA->id,
            'department_id' => $this->deptA->id,
            'name' => 'Worker Alpha',
            'board_visibility' => 'public',
        ]);

        $this->adminB = User::factory()->admin()->create([
            'tenant_id' => $this->tenantB->id,
            'department_id' => $this->deptB->id,
            'name' => 'Admin Beta',
            'board_visibility' => 'public',
        ]);
        $this->workerB = User::factory()->worker()->create([
            'tenant_id' => $this->tenantB->id,
            'department_id' => $this->deptB->id,
            'name' => 'Worker Beta',
            'board_visibility' => 'public',
        ]);

        $this->postItA = PostIt::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'department_id' => $this->deptA->id,
            'requester_id' => $this->adminA->id,
            'assignee_id' => $this->workerA->id,
            'title' => 'Tenant A Critical Task',
            'status' => 'assigned',
            'weight' => 2,
        ]);

        $this->postItB = PostIt::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'department_id' => $this->deptB->id,
            'requester_id' => $this->adminB->id,
            'assignee_id' => $this->workerB->id,
            'title' => 'Tenant B Confidential Task',
            'status' => 'assigned',
            'weight' => 3,
        ]);
    }

    /**
     * Test that an authenticated user of Tenant A can only query Department and PostIt
     * records belonging to Tenant A, and cannot see Tenant B records via Eloquent scopes.
     */
    public function test_authenticated_user_can_only_query_departments_and_post_its_of_own_tenant(): void
    {
        $this->actingAs($this->workerA);

        // Department query isolation
        $departments = Department::all();
        $this->assertTrue($departments->contains('id', $this->deptA->id));
        $this->assertFalse($departments->contains('id', $this->deptB->id));
        $this->assertNull(Department::find($this->deptB->id));

        // PostIt query isolation
        $postIts = PostIt::all();
        $this->assertTrue($postIts->contains('id', $this->postItA->id));
        $this->assertFalse($postIts->contains('id', $this->postItB->id));
        $this->assertNull(PostIt::find($this->postItB->id));

        // Automatic tenant_id assignment when creating records without explicit tenant_id
        $createdPostIt = PostIt::create([
            'requester_id' => $this->workerA->id,
            'title' => 'Implicit Tenant PostIt',
            'status' => 'backlog',
            'priority' => 'normal',
            'weight' => 1,
        ]);

        $this->assertEquals($this->tenantA->id, $createdPostIt->tenant_id);

        // Switch to Tenant B user and confirm inversion of visibility
        $this->actingAs($this->workerB);

        $deptBQuery = Department::all();
        $this->assertFalse($deptBQuery->contains('id', $this->deptA->id));
        $this->assertTrue($deptBQuery->contains('id', $this->deptB->id));

        $postBQuery = PostIt::all();
        $this->assertFalse($postBQuery->contains('id', $this->postItA->id));
        $this->assertTrue($postBQuery->contains('id', $this->postItB->id));
    }

    /**
     * Test that an authenticated user of Tenant A cannot mutate, move, or start a PostIt
     * belonging to Tenant B on the CapacityBoard.
     */
    public function test_authenticated_user_cannot_mutate_or_move_post_it_belonging_to_another_tenant(): void
    {
        // Acting as Admin of Tenant A
        $this->actingAs($this->adminA);

        // Attempting to move Tenant B's Post-it returns 404 due to tenant constraint
        Livewire::test(CapacityBoard::class)
            ->call('movePostIt', $this->postItB->id, $this->workerA->id, 'assigned')
            ->assertStatus(404);

        // Attempting to start Tenant B's Post-it returns 404
        Livewire::test(CapacityBoard::class)
            ->call('startPostIt', $this->postItB->id)
            ->assertStatus(404);

        // Verify Tenant B's Post-it was unchanged in the database
        $freshPostItB = PostIt::withoutGlobalScopes()->find($this->postItB->id);
        $this->assertNotNull($freshPostItB);
        $this->assertEquals('assigned', $freshPostItB->status);
        $this->assertEquals($this->workerB->id, $freshPostItB->assignee_id);
        $this->assertNull($freshPostItB->started_at);
    }

    /**
     * Test that an authenticated user of Tenant A cannot inspect or open the slide-over
     * for a PostIt belonging to Tenant B.
     */
    public function test_authenticated_user_cannot_view_or_inspect_slide_over_of_another_tenant_post_it(): void
    {
        $this->actingAs($this->adminA);

        Livewire::test(CapacityBoard::class)
            ->call('openSlideOver', $this->postItB->id)
            ->assertSet('showSlideOver', false)
            ->assertSet('selectedPostItId', null);
    }

    /**
     * Test that an Admin in TeamManagement cannot view, edit, or delete departments
     * or users of another tenant.
     */
    public function test_admin_in_team_management_cannot_view_or_mutate_other_tenant_departments_or_users(): void
    {
        $this->actingAs($this->adminA);

        Livewire::test(TeamManagement::class)
            ->assertSee($this->deptA->name)
            ->assertDontSee($this->deptB->name)
            ->assertSee($this->workerA->name)
            ->assertDontSee($this->workerB->name);

        // Attempt to delete Tenant B department returns 404
        Livewire::test(TeamManagement::class)
            ->call('deleteDepartment', $this->deptB->id)
            ->assertStatus(404);

        // Attempt to delete Tenant B user returns 404
        Livewire::test(TeamManagement::class)
            ->call('deleteUser', $this->workerB->id)
            ->assertStatus(404);

        // Ensure records still exist in database
        $this->assertDatabaseHas('departments', ['id' => $this->deptB->id]);
        $this->assertDatabaseHas('users', ['id' => $this->workerB->id]);
    }
}
