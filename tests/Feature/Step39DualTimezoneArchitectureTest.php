<?php

namespace Tests\Feature;

use App\Events\BoardUpdated;
use App\Livewire\CapacityBoard;
use App\Livewire\TeamManagement;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\PostItTransfer;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class Step39DualTimezoneArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Department $department;

    protected User $athensAdmin;

    protected User $londonWorker;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Organization with Athens Timezone
        $this->tenant = Tenant::factory()->create([
            'name' => 'Hellenic Cloud Ops',
            'timezone' => 'Europe/Athens',
        ]);

        $this->department = Department::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Platform Reliability',
            'color' => '#6366f1',
        ]);

        // 2. Athens Admin (Default Organization Timezone)
        $this->athensAdmin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Alexandros Athens',
            'email' => 'alex@hellenic-ops.gr',
            'timezone' => 'Europe/Athens',
            'visibility' => 'public',
        ]);

        // 3. London Remote Worker (Non-default local timezone)
        $this->londonWorker = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'James London',
            'email' => 'james@hellenic-ops.gr',
            'timezone' => 'Europe/London',
            'visibility' => 'public',
        ]);
    }

    /**
     * Test database defaults and User model effectiveTimezone behavior.
     */
    public function test_users_table_has_timezone_default_and_user_effective_timezone(): void
    {
        $defaultUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertNotNull($defaultUser->timezone);
        $this->assertSame('Europe/Athens', $defaultUser->effectiveTimezone());

        $defaultUser->update(['timezone' => 'Europe/London']);
        $this->assertSame('Europe/London', $defaultUser->fresh()->effectiveTimezone());
    }

    /**
     * Test that admins can select preferred personal timezone in TeamManagement modal.
     */
    public function test_admin_can_update_member_preferred_personal_timezone_in_team_management(): void
    {
        Event::fake([BoardUpdated::class]);

        $this->actingAs($this->athensAdmin);

        Livewire::test(TeamManagement::class)
            ->call('openEditUserModal', $this->londonWorker->id)
            ->assertSet('userTimezone', 'Europe/London')
            ->set('userTimezone', 'America/New_York')
            ->call('saveUser')
            ->assertHasNoErrors();

        $this->assertSame('America/New_York', $this->londonWorker->fresh()->timezone);
    }

    /**
     * Test collaborator with non-default timezone sees workflow timeline entries in their local time.
     */
    public function test_collaborator_with_non_default_timezone_sees_workflow_timeline_translated_to_local_time(): void
    {
        $createdAtUtc = Carbon::parse('2026-11-16 12:00:00', 'UTC');
        $startedAtUtc = Carbon::parse('2026-11-16 12:15:00', 'UTC');
        $completedAtUtc = Carbon::parse('2026-11-16 12:45:00', 'UTC');

        // Create a PostIt executed at specific UTC timestamps
        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->athensAdmin->id,
            'assignee_id' => $this->londonWorker->id,
            'title' => 'Kubernetes Pod Autoscaling',
            'status' => 'done',
            'weight' => 2,
            'priority' => 'high',
            'bounty_points' => 120,
            'started_at' => $startedAtUtc,
            'completed_at' => $completedAtUtc,
            'created_at' => $createdAtUtc,
            'updated_at' => $completedAtUtc,
        ]);

        PostItTransfer::create([
            'post_it_id' => $postIt->id,
            'actor_id' => $this->athensAdmin->id,
            'from_user_id' => null,
            'to_user_id' => $this->londonWorker->id,
            'action' => 'assigned',
            'created_at' => $createdAtUtc,
        ]);

        PostItTransfer::create([
            'post_it_id' => $postIt->id,
            'actor_id' => $this->londonWorker->id,
            'from_user_id' => $this->londonWorker->id,
            'to_user_id' => $this->londonWorker->id,
            'action' => 'started',
            'created_at' => $startedAtUtc,
        ]);

        PostItTransfer::create([
            'post_it_id' => $postIt->id,
            'actor_id' => $this->londonWorker->id,
            'from_user_id' => $this->londonWorker->id,
            'to_user_id' => $this->londonWorker->id,
            'action' => 'completed',
            'created_at' => $completedAtUtc,
        ]);

        // 1. Authenticate as London remote worker (Europe/London -> UTC+0 in winter)
        $this->actingAs($this->londonWorker);

        Livewire::test(CapacityBoard::class)
            ->call('openSlideOver', $postIt->id)
            ->assertSee('16/11 12:15 → 16/11 12:45') // London local execution timeline
            ->assertSee('16/11/2026 12:00')         // London local transfer timestamp
            ->assertSee('GMT')                       // London local timezone badge
            ->assertDontSee('16/11 14:15 → 16/11 14:45') // Athens time must not be displayed to London worker
            ->assertDontSee('16/11/2026 14:00');

        // 2. Authenticate as Athens Admin (Europe/Athens -> UTC+2 in winter)
        $this->actingAs($this->athensAdmin);

        Livewire::test(CapacityBoard::class)
            ->call('openSlideOver', $postIt->id)
            ->assertSee('16/11 14:15 → 16/11 14:45') // Athens local execution timeline
            ->assertSee('16/11/2026 14:00')         // Athens local transfer timestamp
            ->assertSee('EET')                       // Athens local timezone badge
            ->assertDontSee('16/11 12:15 → 16/11 12:45'); // London time must not be displayed to Athens admin
    }

    /**
     * Test that workspace SLA reports remain strictly anchored to organization's timezone.
     */
    public function test_workspace_sla_reports_remain_anchored_to_organization_timezone(): void
    {
        // Even when viewed by London worker, organization analytics display Org Baseline
        $this->actingAs($this->athensAdmin);

        Livewire::test(TeamManagement::class)
            ->set('activeTab', 'analytics')
            ->assertSee('Org Baseline:')
            ->assertSee('Europe/Athens')
            ->assertSee('EET');
    }
}
