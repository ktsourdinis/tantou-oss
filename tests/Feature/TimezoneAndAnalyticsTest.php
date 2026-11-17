<?php

namespace Tests\Feature;

use App\Livewire\CapacityBoard;
use App\Livewire\TeamManagement;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class TimezoneAndAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test User::effectiveTimezone() inherits tenant timezone or user override.
     */
    public function test_user_effective_timezone_inherits_tenant_or_uses_override(): void
    {
        $tenant = Tenant::create([
            'name' => 'Acme Global',
            'slug' => 'acme-global',
            'timezone' => 'Europe/Berlin',
        ]);

        $inheritedUser = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Hans M.',
            'email' => 'hans@acme.test',
            'password' => bcrypt('Password123!@#'),
            'role' => 'worker',
            'timezone' => null,
        ]);

        $overrideUser = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Elena London',
            'email' => 'elena@acme.test',
            'password' => bcrypt('Password123!@#'),
            'role' => 'worker',
            'timezone' => 'Europe/London',
        ]);

        $this->assertEquals('Europe/Berlin', $inheritedUser->effectiveTimezone());
        $this->assertEquals('Europe/London', $overrideUser->effectiveTimezone());
    }

    /**
     * Test transitioning PostIt to 'doing' sets started_at automatically.
     */
    public function test_post_it_sets_started_at_automatically_when_entering_doing_status(): void
    {
        $tenant = Tenant::create([
            'name' => 'Demo Org',
            'slug' => 'demo-org',
            'timezone' => 'Europe/Athens',
        ]);

        $worker = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Worker 1',
            'email' => 'worker1@demo.test',
            'password' => bcrypt('Password123!@#'),
            'role' => 'worker',
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'requester_id' => $worker->id,
            'assignee_id' => $worker->id,
            'title' => 'Test PostIt SLA',
            'status' => 'assigned',
            'priority' => 'normal',
            'weight' => 2,
            'started_at' => null,
        ]);

        $this->assertNull($postIt->started_at);

        // Transition to doing
        $postIt->update(['status' => 'doing']);

        $postIt->refresh();
        $this->assertNotNull($postIt->started_at);
        $this->assertInstanceOf(Carbon::class, $postIt->started_at);

        // Subsequent update does not overwrite existing started_at
        $originalStartedAt = $postIt->started_at;
        Carbon::setTestNow(now()->addMinutes(10));
        $postIt->update(['title' => 'Updated Title']);
        $postIt->refresh();
        $this->assertEquals($originalStartedAt->timestamp, $postIt->started_at->timestamp);
        Carbon::setTestNow();
    }

    /**
     * Test CapacityBoard startWorking action sets started_at.
     */
    public function test_capacity_board_start_working_sets_started_at(): void
    {
        $tenant = Tenant::create([
            'name' => 'Demo Org',
            'slug' => 'demo-org',
            'timezone' => 'Europe/Athens',
        ]);

        $worker = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Worker 1',
            'email' => 'worker1@demo.test',
            'password' => bcrypt('Password123!@#'),
            'role' => 'worker',
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'requester_id' => $worker->id,
            'assignee_id' => $worker->id,
            'title' => 'Task to start',
            'status' => 'assigned',
            'priority' => 'normal',
            'weight' => 2,
            'started_at' => null,
        ]);

        $this->actingAs($worker);

        Livewire::test(CapacityBoard::class)
            ->call('startWorking', $postIt->id);

        $postIt->refresh();
        $this->assertEquals('doing', $postIt->status);
        $this->assertNotNull($postIt->started_at);
    }

    /**
     * Test Analytics & SLA computations in TeamManagement.
     */
    public function test_team_management_analytics_computes_accurate_kpis_and_breakdowns(): void
    {
        $tenant = Tenant::create([
            'name' => 'MedCenter Ops',
            'slug' => 'medcenter-ops',
            'timezone' => 'Europe/Athens',
        ]);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@medcenter.test',
            'password' => bcrypt('Password123!@#'),
            'role' => 'admin',
        ]);

        $dept = Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'Diagnostics',
            'slug' => 'diagnostics',
            'color' => '#10b981',
        ]);

        $worker = User::create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'name' => 'Nikos Tech',
            'email' => 'nikos@medcenter.test',
            'password' => bcrypt('Password123!@#'),
            'role' => 'worker',
        ]);

        $now = now();

        // 1. Completed task: Radiology Unit, created 2h ago, started 1.5h ago (wait: 30m), completed 30m ago (res: 60m)
        PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'requester_id' => $admin->id,
            'assignee_id' => $worker->id,
            'facility_name' => 'Radiology Unit',
            'title' => 'Calibrate X-Ray Machine',
            'status' => 'done',
            'priority' => 'high',
            'weight' => 3,
            'bounty_points' => 40,
            'created_at' => $now->copy()->subMinutes(120),
            'started_at' => $now->copy()->subMinutes(90),
            'completed_at' => $now->copy()->subMinutes(30),
            'is_canceled' => false,
        ]);

        // 2. Completed task: Central Lab, created 3h ago, started 2.5h ago (wait: 30m), completed 1h ago (res: 90m)
        PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'requester_id' => $admin->id,
            'assignee_id' => $worker->id,
            'facility_name' => 'Central Lab',
            'title' => 'Centrifuge maintenance',
            'status' => 'done',
            'priority' => 'urgent',
            'weight' => 4,
            'bounty_points' => 60,
            'created_at' => $now->copy()->subMinutes(180),
            'started_at' => $now->copy()->subMinutes(150),
            'completed_at' => $now->copy()->subMinutes(60),
            'is_canceled' => false,
        ]);

        // 3. Canceled task: Central Lab, created 1h ago, completed 30m ago, not started (wait/res: null, is_canceled: true)
        PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
            'requester_id' => $admin->id,
            'facility_name' => 'Central Lab',
            'title' => 'Duplicate requisition',
            'status' => 'done',
            'priority' => 'low',
            'weight' => 1,
            'bounty_points' => 0,
            'created_at' => $now->copy()->subMinutes(60),
            'started_at' => null,
            'completed_at' => $now->copy()->subMinutes(30),
            'is_canceled' => true,
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(TeamManagement::class)
            ->call('setActiveTab', 'analytics')
            ->set('analyticsPeriod', '30d');

        $component->assertSee('Analytics & SLA Performance')
            ->assertSee('Mean Time to Start (MTTA)')
            ->assertSee('Mean Time to Resolution (MTTR)')
            ->assertSee('Radiology Unit')
            ->assertSee('Central Lab');

        $analytics = $component->viewData('analytics');
        $this->assertNotEmpty($analytics);

        // MTTA: (30m + 30m) / 2 = 30m
        $this->assertEquals(30.0, $analytics['kpis']['mttaMinutes']);
        $this->assertEquals('30m', $analytics['kpis']['mttaFormatted']);

        // MTTR: (60m + 90m) / 2 = 75m = 1h 15m
        $this->assertEquals(75.0, $analytics['kpis']['mttrMinutes']);
        $this->assertEquals('1h 15m', $analytics['kpis']['mttrFormatted']);

        // Completion rate: 2 completed / 3 total done = 66.7%
        $this->assertEquals(2, $analytics['kpis']['completedCount']);
        $this->assertEquals(1, $analytics['kpis']['canceledCount']);
        $this->assertEquals(66.7, $analytics['kpis']['completionRate']);

        // Urgent count: 1 urgent + 1 high = 2 / 3 = 66.7%
        $this->assertEquals(2, $analytics['kpis']['urgentHighCount']);
        $this->assertEquals(66.7, $analytics['kpis']['urgentRatio']);

        // Facility breakdown
        $facilities = collect($analytics['facilities'])->keyBy('name');
        $this->assertTrue($facilities->has('Central Lab'));
        $this->assertTrue($facilities->has('Radiology Unit'));
        $this->assertEquals(5, $facilities['Central Lab']['totalWeight']); // 4 + 1
        $this->assertEquals(3, $facilities['Radiology Unit']['totalWeight']); // 3
    }

    /**
     * Test exportAnalyticsCsv produces a valid CSV download for admins.
     */
    public function test_export_analytics_csv_streams_valid_utf8_bom_file(): void
    {
        $tenant = Tenant::create([
            'name' => 'Export Tenant',
            'slug' => 'export-tenant',
            'timezone' => 'Europe/Athens',
        ]);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Export',
            'email' => 'admin@export.test',
            'password' => bcrypt('Password123!@#'),
            'role' => 'admin',
        ]);

        $worker = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Worker Export',
            'email' => 'worker@export.test',
            'password' => bcrypt('Password123!@#'),
            'role' => 'worker',
        ]);

        PostIt::create([
            'tenant_id' => $tenant->id,
            'requester_id' => $admin->id,
            'assignee_id' => $worker->id,
            'facility_name' => 'Cardiology',
            'tracking_token' => 'TNT-EXP001',
            'title' => 'ECG machine network sync',
            'status' => 'done',
            'priority' => 'high',
            'weight' => 3,
            'bounty_points' => 45,
            'created_at' => now()->subHours(2),
            'started_at' => now()->subHours(2)->addMinutes(15),
            'completed_at' => now()->subHour(),
            'is_canceled' => false,
        ]);

        $this->actingAs($admin);

        Livewire::test(TeamManagement::class)
            ->call('exportAnalyticsCsv')
            ->assertFileDownloaded("tantou-analytics-{$tenant->slug}-30d.csv");

        // Verify CSV content & UTF-8 BOM
        $component = new TeamManagement;
        $component->tenantId = $tenant->id;
        $component->analyticsPeriod = '30d';

        $response = $component->exportAnalyticsCsv();
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Tracking Code', $content);
        $this->assertStringContainsString('TNT-EXP001', $content);
        $this->assertStringContainsString('Cardiology', $content);
        $this->assertStringContainsString('ECG machine network sync', $content);
        $this->assertStringContainsString('Completed', $content);
    }

    /**
     * Test regular workers cannot access Analytics tab or export CSV.
     */
    public function test_worker_cannot_access_analytics_or_export_csv(): void
    {
        $tenant = Tenant::create([
            'name' => 'Restricted Org',
            'slug' => 'restricted-org',
            'timezone' => 'Europe/Athens',
        ]);

        $worker = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Regular Worker',
            'email' => 'regular@restricted.test',
            'password' => bcrypt('Password123!@#'),
            'role' => 'worker',
        ]);

        $this->actingAs($worker);

        // Attempting to switch to analytics tab should be rejected
        $test = Livewire::test(TeamManagement::class)
            ->call('setActiveTab', 'analytics');

        $this->assertNotEquals('analytics', $test->get('activeTab'));

        // Attempting to export CSV should abort 403
        $test->call('exportAnalyticsCsv')->assertStatus(403);
    }

    /**
     * Test CapacityBoard displays remote collaborator local time badge when timezones differ.
     */
    public function test_capacity_board_renders_remote_collaborator_local_time_badge(): void
    {
        $tenant = Tenant::create([
            'name' => 'Global Org',
            'slug' => 'global-org',
            'timezone' => 'Europe/Athens',
        ]);

        $viewer = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Athens Viewer',
            'email' => 'athens@global.test',
            'password' => bcrypt('Password123!@#'),
            'role' => 'admin',
            'timezone' => 'Europe/Athens',
        ]);

        $remoteWorker = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'London Worker',
            'email' => 'london@global.test',
            'password' => bcrypt('Password123!@#'),
            'role' => 'worker',
            'board_visibility' => 'public',
            'is_available' => true,
            'timezone' => 'Europe/London',
        ]);

        $this->actingAs($viewer);

        $test = Livewire::test(CapacityBoard::class);

        // Remote worker's local time badge is rendered with London timezone offset
        $londonTime = now()->timezone('Europe/London')->format('H:i');
        $test->assertSee($londonTime);
    }
}
