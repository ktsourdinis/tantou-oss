<?php

namespace Tests\Feature;

use App\Livewire\CapacityBoard;
use App\Livewire\PublicSubmitRequest;
use App\Livewire\PublicTrackRequest;
use App\Livewire\TeamManagement;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class LivewirePropertyTamperingSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $adminA;

    private User $workerA;

    private User $adminB;

    private User $workerB;

    private Department $deptA;

    private Department $deptB;

    private PostIt $postItA;

    private PostIt $postItB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'name' => 'Tenant Alpha',
            'slug' => 'tenant-alpha',
            'public_submission_enabled' => true,
        ]);

        $this->tenantB = Tenant::factory()->create([
            'name' => 'Tenant Beta',
            'slug' => 'tenant-beta',
            'public_submission_enabled' => true,
        ]);

        $this->deptA = Department::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Operations',
            'slug' => 'alpha-ops',
        ]);

        $this->deptB = Department::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Operations',
            'slug' => 'beta-ops',
        ]);

        $this->adminA = User::factory()->admin()->create([
            'tenant_id' => $this->tenantA->id,
            'department_id' => $this->deptA->id,
            'email' => 'admin.a@example.com',
        ]);

        $this->workerA = User::factory()->worker()->create([
            'tenant_id' => $this->tenantA->id,
            'department_id' => $this->deptA->id,
            'email' => 'worker.a@example.com',
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        $this->adminB = User::factory()->admin()->create([
            'tenant_id' => $this->tenantB->id,
            'department_id' => $this->deptB->id,
            'email' => 'admin.b@example.com',
        ]);

        $this->workerB = User::factory()->worker()->create([
            'tenant_id' => $this->tenantB->id,
            'department_id' => $this->deptB->id,
            'email' => 'worker.b@example.com',
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        $this->postItA = PostIt::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'department_id' => $this->deptA->id,
            'requester_id' => $this->workerA->id,
            'assignee_id' => $this->workerA->id,
            'status' => 'assigned',
            'tracking_token' => 'TNT-SEC-ALPHA-01',
            'title' => 'Alpha Internal Task',
        ]);

        $this->postItB = PostIt::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'department_id' => $this->deptB->id,
            'requester_id' => $this->workerB->id,
            'assignee_id' => $this->workerB->id,
            'status' => 'assigned',
            'tracking_token' => 'TNT-SEC-BETA-02',
            'title' => 'Beta Private Task',
        ]);
    }

    /**
     * 1. Public Tracker IDOR Test:
     * Attempting to tamper with postItId on PublicTrackRequest throws CannotUpdateLockedPropertyException.
     */
    public function test_public_tracker_rejects_post_it_id_tampering_via_locked_property(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(PublicTrackRequest::class, ['token' => 'TNT-SEC-ALPHA-01'])
            ->set('postItId', $this->postItB->id);
    }

    /**
     * 1b. Public Tracker Token Tampering:
     * Attempting to mutate token throws CannotUpdateLockedPropertyException.
     */
    public function test_public_tracker_rejects_token_tampering_via_locked_property(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(PublicTrackRequest::class, ['token' => 'TNT-SEC-ALPHA-01'])
            ->set('token', 'TNT-SEC-BETA-02');
    }

    /**
     * 1c. Public Tracker IDOR Isolation:
     * Tracker actions execute strictly against the resolved tracking token and never affect other tenants.
     */
    public function test_public_tracker_actions_scope_strictly_to_tracking_token(): void
    {
        Livewire::test(PublicTrackRequest::class, ['token' => 'TNT-SEC-ALPHA-01'])
            ->call('closeByRequester', 'Completed by guest')
            ->call('addComment');

        $this->postItA->refresh();
        $this->postItB->refresh();

        // PostIt A is completed
        $this->assertEquals('done', $this->postItA->status);

        // PostIt B in Tenant B remains strictly assigned, unaffected, with 0 comments
        $this->assertEquals('assigned', $this->postItB->status);
        $this->assertCount(0, $this->postItB->comments);
    }

    /**
     * 2. CapacityBoard Cross-Tenant ID Tampering:
     * Attempting to tamper with tenantId throws CannotUpdateLockedPropertyException.
     */
    public function test_capacity_board_rejects_tenant_id_tampering(): void
    {
        $this->actingAs($this->adminA);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(CapacityBoard::class)
            ->set('tenantId', $this->tenantB->id);
    }

    /**
     * 2b. CapacityBoard Cross-Tenant Task Actions:
     * Passing another tenant's PostIt ID to completeTask throws ModelNotFoundException.
     */
    public function test_capacity_board_rejects_cross_tenant_complete_task(): void
    {
        $this->actingAs($this->adminA);

        $testable = Livewire::test(CapacityBoard::class);

        try {
            $testable->instance()->completeTask($this->postItB->id);
            $this->fail('Expected ModelNotFoundException was not thrown.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $testable->call('completeTask', $this->postItB->id);

        $this->postItB->refresh();
        $this->assertEquals('assigned', $this->postItB->status);
    }

    /**
     * 2c. CapacityBoard Cross-Tenant Task Actions:
     * Passing another tenant's PostIt ID to startTask throws ModelNotFoundException.
     */
    public function test_capacity_board_rejects_cross_tenant_start_task(): void
    {
        $this->actingAs($this->adminA);

        $testable = Livewire::test(CapacityBoard::class);

        try {
            $testable->instance()->startTask($this->postItB->id);
            $this->fail('Expected ModelNotFoundException was not thrown.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $testable->call('startTask', $this->postItB->id);

        $this->postItB->refresh();
        $this->assertEquals('assigned', $this->postItB->status);
        $this->assertNull($this->postItB->started_at);
    }

    /**
     * 2d. CapacityBoard Cross-Tenant Task Actions:
     * Passing another tenant's PostIt ID to openPostItModal throws ModelNotFoundException.
     */
    public function test_capacity_board_rejects_cross_tenant_open_post_it_modal(): void
    {
        $this->actingAs($this->adminA);

        $testable = Livewire::test(CapacityBoard::class);

        try {
            $testable->instance()->openPostItModal($this->postItB->id);
            $this->fail('Expected ModelNotFoundException was not thrown.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $testable->call('openPostItModal', $this->postItB->id);
    }

    /**
     * 2e. CapacityBoard Cross-Tenant Task Actions:
     * Calling openSlideOver with another tenant's PostIt ID does not open or set state.
     */
    public function test_capacity_board_open_slide_over_ignores_cross_tenant_post_it(): void
    {
        $this->actingAs($this->adminA);

        Livewire::test(CapacityBoard::class)
            ->call('openSlideOver', $this->postItB->id)
            ->assertSet('showSlideOver', false)
            ->assertSet('selectedPostItId', null);
    }

    /**
     * 2f. CapacityBoard Cross-Tenant Task Actions:
     * Passing another tenant's PostIt ID to cancelTask throws ModelNotFoundException.
     */
    public function test_capacity_board_rejects_cross_tenant_cancel_task(): void
    {
        $this->actingAs($this->adminA);

        $testable = Livewire::test(CapacityBoard::class);

        try {
            $testable->instance()->cancelTask($this->postItB->id, 'Malicious cancel');
            $this->fail('Expected ModelNotFoundException was not thrown.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $testable->call('cancelTask', $this->postItB->id, 'Malicious cancel');

        $this->postItB->refresh();
        $this->assertEquals('assigned', $this->postItB->status);
    }

    /**
     * 3. TeamManagement Cross-Tenant Tampering:
     * Attempting to mutate tenantId or editingUserId throws CannotUpdateLockedPropertyException.
     */
    public function test_team_management_rejects_tenant_id_tampering(): void
    {
        $this->actingAs($this->adminA);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(TeamManagement::class)
            ->set('tenantId', $this->tenantB->id);
    }

    public function test_team_management_rejects_editing_user_id_tampering(): void
    {
        $this->actingAs($this->adminA);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(TeamManagement::class)
            ->set('editingUserId', $this->workerB->id);
    }

    public function test_team_management_rejects_editing_department_id_tampering(): void
    {
        $this->actingAs($this->adminA);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(TeamManagement::class)
            ->set('editingDepartmentId', $this->deptB->id);
    }

    /**
     * 3b. TeamManagement Cross-Tenant Deletion / Editing:
     * Attempting to delete or edit a member of Tenant B throws ModelNotFoundException.
     */
    public function test_team_management_rejects_cross_tenant_user_deletion(): void
    {
        $this->actingAs($this->adminA);

        $testable = Livewire::test(TeamManagement::class);

        try {
            $testable->instance()->deleteMember($this->workerB->id);
            $this->fail('Expected ModelNotFoundException was not thrown.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $testable->call('deleteMember', $this->workerB->id);

        $this->assertDatabaseHas('users', ['id' => $this->workerB->id]);
    }

    public function test_team_management_rejects_cross_tenant_user_edit(): void
    {
        $this->actingAs($this->adminA);

        $testable = Livewire::test(TeamManagement::class);

        try {
            $testable->instance()->editMember($this->workerB->id);
            $this->fail('Expected ModelNotFoundException was not thrown.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $testable->call('editMember', $this->workerB->id);
    }

    /**
     * 3c. TeamManagement Cross-Tenant Department Deletion / Editing:
     * Attempting to delete or edit a department of Tenant B throws ModelNotFoundException.
     */
    public function test_team_management_rejects_cross_tenant_department_deletion(): void
    {
        $this->actingAs($this->adminA);

        $testable = Livewire::test(TeamManagement::class);

        try {
            $testable->instance()->deleteDepartment($this->deptB->id);
            $this->fail('Expected ModelNotFoundException was not thrown.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $testable->call('deleteDepartment', $this->deptB->id);

        $this->assertDatabaseHas('departments', ['id' => $this->deptB->id]);
    }

    public function test_team_management_rejects_cross_tenant_department_edit(): void
    {
        $this->actingAs($this->adminA);

        $testable = Livewire::test(TeamManagement::class);

        try {
            $testable->instance()->editDepartment($this->deptB->id);
            $this->fail('Expected ModelNotFoundException was not thrown.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $testable->call('editDepartment', $this->deptB->id);
    }

    /**
     * 4. PublicSubmitRequest Tampering:
     * Attempting to mutate tenantId on PublicSubmitRequest throws CannotUpdateLockedPropertyException.
     */
    public function test_public_submit_request_rejects_tenant_id_tampering(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(PublicSubmitRequest::class, ['tenantSlug' => 'tenant-alpha'])
            ->set('tenantId', $this->tenantB->id);
    }
}
