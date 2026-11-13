<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\PostIt;
use App\Models\PostItTransfer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BountyCalculator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $calculator = app(BountyCalculator::class);

        // Clean up prior seed data for clean re-seeding
        PostItTransfer::query()->delete();
        PostIt::query()->delete();
        User::query()->delete();
        Department::query()->delete();
        Tenant::query()->delete();

        $tenant = Tenant::factory()->create([
            'name' => 'Hellenic Demo Ops',
            'slug' => 'hellenic-demo-ops',
            'settings' => [
                'timezone' => 'Europe/Athens',
                'theme' => 'dark',
            ],
        ]);

        // Seed 3 core Departments under Hellenic Demo Ops
        $softwareDept = Department::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Software',
            'slug' => 'software',
            'color' => '#3b82f6',
        ]);

        $infraDept = Department::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Υποδομές & Δίκτυα',
            'slug' => 'ypodomes-diktya',
            'color' => '#10b981',
        ]);

        $opsDept = Department::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Operations',
            'slug' => 'operations',
            'color' => '#f59e0b',
        ]);

        $admin = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $opsDept->id,
            'name' => 'Alexandros Admin',
            'email' => 'admin@tantou.local',
            'password' => 'password',
            'board_visibility' => 'private',
            'is_available' => true,
        ]);

        $client = User::factory()->client()->create([
            'tenant_id' => $tenant->id,
            'department_id' => null,
            'name' => 'Sofia Client',
            'email' => 'client@tantou.local',
            'password' => 'password',
            'board_visibility' => 'none',
            'is_available' => true,
        ]);

        // Coordinator (Team Lead) for Software Department
        $nikos = User::factory()->coordinator()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'name' => 'Nikos Pappas',
            'email' => 'nikos@tantou.local',
            'password' => 'password',
            'board_visibility' => 'public',
            'is_available' => true,
            'max_capacity' => 10,
        ]);

        // Public Worker in Software Department
        $elena = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'name' => 'Elena Dimitriou',
            'email' => 'elena@tantou.local',
            'password' => 'password',
            'board_visibility' => 'public',
            'is_available' => true,
            'max_capacity' => 10,
        ]);

        // Public Worker in Infra Department
        $kostas = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $infraDept->id,
            'name' => 'Kostas Karalis',
            'email' => 'kostas@tantou.local',
            'password' => 'password',
            'board_visibility' => 'public',
            'is_available' => true,
            'max_capacity' => 10,
        ]);

        // Internal Column Collaborator in Operations Department
        $maria = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $opsDept->id,
            'name' => 'Maria Oikonomou',
            'email' => 'maria@tantou.local',
            'password' => 'password',
            'board_visibility' => 'internal',
            'is_available' => true,
            'max_capacity' => 8,
        ]);

        // Away Collaborator in Software Department
        $giorgos = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'name' => 'Giorgos Away',
            'email' => 'giorgos@tantou.local',
            'password' => 'password',
            'board_visibility' => 'public',
            'is_available' => false,
            'max_capacity' => 10,
        ]);

        // 1. Completed Post-Its (Done)
        $done1 = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $infraDept->id,
            'requester_id' => $admin->id,
            'assignee_id' => $nikos->id,
            'assigned_by_id' => $admin->id,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Scaffold TALL stack skeleton & Docker compose services',
            'description' => 'Setup PHP 8.4, MariaDB 11, Redis, and Reverb container definitions.',
            'status' => 'done',
            'priority' => 'high',
            'weight' => 3,
            'bounty_points' => $calculator->calculate(3, 'high', $tenant->id),
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subDays(1),
        ]);

        PostItTransfer::create([
            'post_it_id' => $done1->id,
            'actor_id' => $admin->id,
            'from_user_id' => null,
            'to_user_id' => $nikos->id,
            'action' => 'assigned',
            'created_at' => now()->subDays(3),
        ]);
        PostItTransfer::create([
            'post_it_id' => $done1->id,
            'actor_id' => $nikos->id,
            'from_user_id' => $nikos->id,
            'to_user_id' => $nikos->id,
            'action' => 'started',
            'created_at' => now()->subDays(2),
        ]);
        PostItTransfer::create([
            'post_it_id' => $done1->id,
            'actor_id' => $nikos->id,
            'from_user_id' => $nikos->id,
            'to_user_id' => $nikos->id,
            'action' => 'completed',
            'created_at' => now()->subDays(1),
        ]);

        $done2 = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'requester_id' => $admin->id,
            'assignee_id' => $elena->id,
            'assigned_by_id' => $admin->id,
            'helper_id' => $kostas->id,
            'helper_split' => 25,
            'title' => 'Setup multi-tenant base migrations & schema validation',
            'description' => 'Create tenants, users capacity columns, and post_its database tables.',
            'status' => 'done',
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => $calculator->calculate(2, 'normal', $tenant->id),
            'started_at' => now()->subDays(3),
            'completed_at' => now()->subDays(2),
        ]);

        // Multi-Hop Completed Task: Nikos -> Elena -> Kostas -> Completed
        $multiHopDone = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'requester_id' => $client->id,
            'assignee_id' => $kostas->id,
            'assigned_by_id' => $elena->id,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Cross-browser WebSocket fallback for legacy mobile browsers',
            'description' => 'Architected reconnect loop, tested SSE fallback, and passed multi-department QA.',
            'status' => 'done',
            'priority' => 'high',
            'weight' => 3,
            'bounty_points' => $calculator->calculate(3, 'high', $tenant->id),
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subHours(6),
        ]);

        PostItTransfer::create([
            'post_it_id' => $multiHopDone->id,
            'actor_id' => $client->id,
            'from_user_id' => null,
            'to_user_id' => $nikos->id,
            'action' => 'assigned',
            'created_at' => now()->subDays(5),
        ]);
        PostItTransfer::create([
            'post_it_id' => $multiHopDone->id,
            'actor_id' => $nikos->id,
            'from_user_id' => $nikos->id,
            'to_user_id' => $elena->id,
            'action' => 'transferred',
            'created_at' => now()->subDays(4),
        ]);
        PostItTransfer::create([
            'post_it_id' => $multiHopDone->id,
            'actor_id' => $elena->id,
            'from_user_id' => $elena->id,
            'to_user_id' => $kostas->id,
            'action' => 'transferred',
            'created_at' => now()->subDays(2),
        ]);
        PostItTransfer::create([
            'post_it_id' => $multiHopDone->id,
            'actor_id' => $kostas->id,
            'from_user_id' => $kostas->id,
            'to_user_id' => $kostas->id,
            'action' => 'started',
            'created_at' => now()->subDays(2)->addHour(),
        ]);
        PostItTransfer::create([
            'post_it_id' => $multiHopDone->id,
            'actor_id' => $kostas->id,
            'from_user_id' => $kostas->id,
            'to_user_id' => $kostas->id,
            'action' => 'completed',
            'created_at' => now()->subHours(6),
        ]);

        // 2. Backlog Post-Its (Unassigned)
        PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'requester_id' => $client->id,
            'assignee_id' => null,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Integrate Stripe billing webhooks for annual subscriptions',
            'description' => 'Listen to subscription lifecycle events and synchronize customer payment states.',
            'status' => 'backlog',
            'priority' => 'high',
            'weight' => 3,
            'bounty_points' => $calculator->calculate(3, 'high', $tenant->id),
        ]);

        PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $opsDept->id,
            'requester_id' => $client->id,
            'assignee_id' => null,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Implement PDF invoice export for EU VAT compliance',
            'description' => 'Generate downloadable PDF receipts containing Hellenic Tax and EU VAT reverse-charge notes.',
            'status' => 'backlog',
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => $calculator->calculate(2, 'normal', $tenant->id),
        ]);

        PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $infraDept->id,
            'requester_id' => $admin->id,
            'assignee_id' => null,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Security audit for multi-tenant data isolation',
            'description' => 'Verify global tenant scoping and foreign key cascade rules across all Eloquent queries.',
            'status' => 'backlog',
            'priority' => 'urgent',
            'weight' => 5,
            'bounty_points' => $calculator->calculate(5, 'urgent', $tenant->id),
        ]);

        // 3. Two-Stage Active Tasks: Assigned (not started, started_at = null) vs Doing (started)

        // Assigned Post-Its (Ready to be started)
        PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'requester_id' => $client->id,
            'assignee_id' => $nikos->id,
            'assigned_by_id' => $client->id,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Benchmark MySQL InnoDB index performance for tenant queries',
            'description' => 'Analyze composite index selectivity on (tenant_id, department_id, status).',
            'status' => 'assigned',
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => $calculator->calculate(2, 'normal', $tenant->id),
            'started_at' => null,
        ]);

        // Forwarded / assigned by Nikos to Elena (shows "Από: Sofia Client" and "Ανάθεση από: Nikos Pappas")
        $assignedElena = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'requester_id' => $client->id,
            'assignee_id' => $elena->id,
            'assigned_by_id' => $nikos->id,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Design interactive capacity progress gauges in Tailwind',
            'description' => 'Add dynamic visual indicators that shift from emerald to amber to rose as capacity fills.',
            'status' => 'assigned',
            'priority' => 'normal',
            'weight' => 3,
            'bounty_points' => $calculator->calculate(3, 'normal', $tenant->id),
            'started_at' => null,
        ]);

        PostItTransfer::create([
            'post_it_id' => $assignedElena->id,
            'actor_id' => $client->id,
            'from_user_id' => null,
            'to_user_id' => $nikos->id,
            'action' => 'assigned',
            'created_at' => now()->subHours(8),
        ]);
        PostItTransfer::create([
            'post_it_id' => $assignedElena->id,
            'actor_id' => $nikos->id,
            'from_user_id' => $nikos->id,
            'to_user_id' => $elena->id,
            'action' => 'transferred',
            'created_at' => now()->subHours(2),
        ]);

        // Multi-Hop Active Task on Board: Nikos -> Elena -> Nikos (Doing)
        $multiHopActive = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'requester_id' => $admin->id,
            'assignee_id' => $nikos->id,
            'assigned_by_id' => $elena->id,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Refactor real-time WebSocket broadcasting events',
            'description' => 'Migrate canvas drag actions to Laravel Reverb private channels for low-latency updates.',
            'status' => 'doing',
            'priority' => 'urgent',
            'weight' => 5,
            'bounty_points' => $calculator->calculate(5, 'urgent', $tenant->id),
            'started_at' => now()->subHours(4),
        ]);

        PostItTransfer::create([
            'post_it_id' => $multiHopActive->id,
            'actor_id' => $admin->id,
            'from_user_id' => null,
            'to_user_id' => $nikos->id,
            'action' => 'assigned',
            'created_at' => now()->subDays(1),
        ]);
        PostItTransfer::create([
            'post_it_id' => $multiHopActive->id,
            'actor_id' => $nikos->id,
            'from_user_id' => $nikos->id,
            'to_user_id' => $elena->id,
            'action' => 'transferred',
            'created_at' => now()->subHours(18),
        ]);
        PostItTransfer::create([
            'post_it_id' => $multiHopActive->id,
            'actor_id' => $elena->id,
            'from_user_id' => $elena->id,
            'to_user_id' => $nikos->id,
            'action' => 'transferred',
            'created_at' => now()->subHours(5),
        ]);
        PostItTransfer::create([
            'post_it_id' => $multiHopActive->id,
            'actor_id' => $nikos->id,
            'from_user_id' => $nikos->id,
            'to_user_id' => $nikos->id,
            'action' => 'started',
            'created_at' => now()->subHours(4),
        ]);

        // Co-op assisted task: Nikos (public) + Kostas (public), 30% split
        PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'requester_id' => $client->id,
            'assignee_id' => $nikos->id,
            'helper_id' => $kostas->id,
            'helper_split' => 30,
            'title' => 'Resolve MariaDB foreign key deadlock in concurrent sessions',
            'description' => 'Optimize transaction locks when multiple workers claim unassigned tickets simultaneously.',
            'status' => 'doing',
            'priority' => 'high',
            'weight' => 2,
            'bounty_points' => $calculator->calculate(2, 'high', $tenant->id),
            'started_at' => now()->subHours(1),
        ]);

        PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'requester_id' => $admin->id,
            'assignee_id' => $elena->id,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Implement optimistic drag-and-drop feedback with Alpine.js',
            'description' => 'Ensure smooth Post-it card animations before the server WebSocket roundtrip finishes.',
            'status' => 'doing',
            'priority' => 'low',
            'weight' => 2,
            'bounty_points' => $calculator->calculate(2, 'low', $tenant->id),
            'started_at' => now()->subHours(2),
        ]);

        PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $infraDept->id,
            'requester_id' => $client->id,
            'assignee_id' => $kostas->id,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Configure Redis cache tags for tenant dashboard stats',
            'description' => 'Cache aggregate tenant metrics and invalidate upon Post-it completion events.',
            'status' => 'doing',
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => $calculator->calculate(2, 'normal', $tenant->id),
            'started_at' => now()->subMinutes(45),
        ]);

        // Personal / Private Task on Admin's column
        PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $opsDept->id,
            'requester_id' => $admin->id,
            'assignee_id' => $admin->id,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Prepare quarterly capacity and SLA executive report',
            'description' => 'Private administrative planning and team capacity projections.',
            'status' => 'assigned',
            'priority' => 'high',
            'weight' => 2,
            'bounty_points' => $calculator->calculate(2, 'high', $tenant->id),
            'started_at' => null,
        ]);

        // Synchronize collaborator current_capacity and total_points (counting both 'assigned' and 'doing')
        $collaborators = [$admin, $nikos, $elena, $kostas, $maria, $giorgos];
        $workerPoints = [
            $admin->id => 0,
            $nikos->id => 0,
            $elena->id => 0,
            $kostas->id => 0,
            $maria->id => 0,
            $giorgos->id => 0,
        ];

        $completedPostIts = PostIt::where('tenant_id', $tenant->id)->where('status', 'done')->get();
        foreach ($completedPostIts as $doneTask) {
            $split = $calculator->calculatePayoutSplit($doneTask);
            if ($doneTask->assignee_id && isset($workerPoints[$doneTask->assignee_id])) {
                $workerPoints[$doneTask->assignee_id] += $split['assignee_points'];
            }
            if ($doneTask->helper_id && isset($workerPoints[$doneTask->helper_id])) {
                $workerPoints[$doneTask->helper_id] += $split['helper_points'];
            }
        }

        foreach ($collaborators as $collab) {
            $collab->update([
                'current_capacity' => (int) $collab->assignedPostIts()->whereIn('status', ['assigned', 'doing'])->sum('weight'),
                'total_points' => $workerPoints[$collab->id] ?? 0,
            ]);
        }
    }
}
