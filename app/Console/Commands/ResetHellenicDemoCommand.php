<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\PostIt;
use App\Models\PostItAttachment;
use App\Models\PostItComment;
use App\Models\PostItTransfer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetHellenicDemoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tantou:reset-demo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset the Hellenic Demo Ops tenant with short, international-friendly names and 100% English demo data.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Resetting Hellenic Demo Ops tenant with international English demo data...');

        // 1. Locate or create the Hellenic Demo Ops tenant strictly by slug
        $tenant = Tenant::where('slug', 'hellenic-demo-ops')->first();

        if ($tenant !== null) {
            // Delete related records scoped strictly to this tenant to leave other tenants untouched
            $postItIds = PostIt::where('tenant_id', $tenant->id)->pluck('id');
            PostItTransfer::whereIn('post_it_id', $postItIds)->delete();
            PostItComment::whereIn('post_it_id', $postItIds)->delete();
            PostItAttachment::whereIn('post_it_id', $postItIds)->delete();
            PostIt::where('tenant_id', $tenant->id)->delete();
            User::where('tenant_id', $tenant->id)->delete();
            Department::where('tenant_id', $tenant->id)->delete();

            $tenant->update([
                'name' => 'Hellenic Demo Ops',
                'public_submission_enabled' => true,
                'strict_client_isolation' => false,
                'settings' => [
                    'timezone' => 'Europe/Athens',
                    'theme' => 'dark',
                ],
            ]);
        } else {
            $tenant = Tenant::create([
                'name' => 'Hellenic Demo Ops',
                'slug' => 'hellenic-demo-ops',
                'public_submission_enabled' => true,
                'strict_client_isolation' => false,
                'settings' => [
                    'timezone' => 'Europe/Athens',
                    'theme' => 'dark',
                ],
            ]);
        }

        // 2. Seed 3 Core Departments (100% Clean English)
        $softwareDept = Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'Software',
            'slug' => 'software',
            'color' => '#3b82f6',
        ]);

        $infraDept = Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'Infrastructure & Net',
            'slug' => 'infrastructure-net',
            'color' => '#10b981',
        ]);

        $opsDept = Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'Operations',
            'slug' => 'operations',
            'color' => '#f59e0b',
        ]);

        // 3. Seed Users with short, international-friendly names and exact bounty scores
        $hashedPassword = Hash::make('password');

        $alex = User::create([
            'tenant_id' => $tenant->id,
            'department_id' => $opsDept->id,
            'name' => 'Alex M.',
            'email' => 'admin@tantou.local',
            'password' => $hashedPassword,
            'role' => 'admin',
            'board_visibility' => 'private',
            'is_available' => true,
            'max_capacity' => 10,
            'current_capacity' => 0,
            'total_points' => 120,
            'locale' => 'en',
        ]);

        $nikos = User::create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'name' => 'Nick Pappas',
            'email' => 'coordinator@tantou.local',
            'password' => $hashedPassword,
            'role' => 'coordinator',
            'board_visibility' => 'public',
            'is_available' => true,
            'max_capacity' => 10,
            'current_capacity' => 0,
            'total_points' => 245,
            'locale' => 'en',
        ]);

        $elena = User::create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'name' => 'Elena D.',
            'email' => 'elena@tantou.local',
            'password' => $hashedPassword,
            'role' => 'worker',
            'board_visibility' => 'public',
            'is_available' => true,
            'max_capacity' => 10,
            'current_capacity' => 0,
            'total_points' => 380,
            'locale' => 'en',
        ]);

        $kostas = User::create([
            'tenant_id' => $tenant->id,
            'department_id' => $infraDept->id,
            'name' => 'Kostas K.',
            'email' => 'kostas@tantou.local',
            'password' => $hashedPassword,
            'role' => 'worker',
            'board_visibility' => 'internal',
            'is_available' => true,
            'max_capacity' => 10,
            'current_capacity' => 0,
            'total_points' => 310,
            'locale' => 'en',
        ]);

        $maria = User::create([
            'tenant_id' => $tenant->id,
            'department_id' => $opsDept->id,
            'name' => 'Maria O.',
            'email' => 'maria@tantou.local',
            'password' => $hashedPassword,
            'role' => 'worker',
            'board_visibility' => 'private',
            'is_available' => true,
            'max_capacity' => 10,
            'current_capacity' => 0,
            'total_points' => 195,
            'locale' => 'en',
        ]);

        $giorgos = User::create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'name' => 'George A.',
            'email' => 'giorgos@tantou.local',
            'password' => $hashedPassword,
            'role' => 'worker',
            'board_visibility' => 'public',
            'is_available' => false,
            'max_capacity' => 10,
            'current_capacity' => 0,
            'total_points' => 90,
            'locale' => 'en',
        ]);

        $sofia = User::create([
            'tenant_id' => $tenant->id,
            'department_id' => null,
            'name' => 'Sofia C.',
            'email' => 'client@tantou.local',
            'password' => $hashedPassword,
            'role' => 'client',
            'board_visibility' => 'none',
            'is_available' => true,
            'facility_name' => 'Radiology Unit',
            'max_capacity' => 10,
            'current_capacity' => 0,
            'total_points' => 0,
            'locale' => 'en',
        ]);

        $lab = User::create([
            'tenant_id' => $tenant->id,
            'department_id' => null,
            'name' => 'Chris L.',
            'email' => 'lab@tantou.local',
            'password' => $hashedPassword,
            'role' => 'client',
            'board_visibility' => 'none',
            'is_available' => true,
            'facility_name' => 'Central Lab',
            'max_capacity' => 10,
            'current_capacity' => 0,
            'total_points' => 0,
            'locale' => 'en',
        ]);

        // 4. Seed Unassigned Backlog (status = 'backlog')
        // 4.1 URGENT / W5 (Infrastructure & Net): Security audit for multi-tenant data isolation (created by Alex M.)
        PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $infraDept->id,
            'requester_id' => $alex->id,
            'assignee_id' => null,
            'assigned_by_id' => null,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Security audit for multi-tenant data isolation',
            'description' => 'Verify global tenant scoping and foreign key cascade rules across all Eloquent queries.',
            'status' => 'backlog',
            'priority' => 'urgent',
            'weight' => 5,
            'bounty_points' => 75,
            'started_at' => null,
            'completed_at' => null,
            'is_canceled' => false,
            'created_at' => now()->subHours(10),
        ]);

        // 4.2 HIGH / W2 (Infrastructure & Net): DICOM printer offline in imaging room 2 (Guest Drop-Box submission)
        PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $infraDept->id,
            'requester_id' => null,
            'guest_name' => 'Dr. Markos',
            'facility_name' => 'Radiology Unit',
            'guest_contact' => 'Ext. 2140',
            'tracking_token' => 'TNT-DEMO2026',
            'assignee_id' => null,
            'assigned_by_id' => null,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'DICOM printer offline in imaging room 2',
            'description' => 'Imaging station network print spooler is unresponsive after firmware restart.',
            'status' => 'backlog',
            'priority' => 'high',
            'weight' => 2,
            'bounty_points' => 25,
            'started_at' => null,
            'completed_at' => null,
            'is_canceled' => false,
            'created_at' => now()->subHours(5),
        ]);

        // 5. Seed Active Collaborator Queues (doing & assigned)

        // --- Elena D. (Software) ---
        // 5.1 doing, HIGH, W3 (41 pts): Integrate Stripe billing webhooks for annual subscriptions
        $elenaDoing = PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'requester_id' => $sofia->id,
            'facility_name' => 'Radiology Unit',
            'assignee_id' => $elena->id,
            'assigned_by_id' => $sofia->id,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Integrate Stripe billing webhooks for annual subscriptions',
            'description' => 'Listen to subscription lifecycle events and synchronize customer payment states.',
            'status' => 'doing',
            'priority' => 'high',
            'weight' => 3,
            'bounty_points' => 41,
            'started_at' => now()->subHours(3),
            'completed_at' => null,
            'is_canceled' => false,
            'created_at' => now()->subHours(8),
        ]);

        PostItTransfer::create([
            'post_it_id' => $elenaDoing->id,
            'actor_id' => $sofia->id,
            'from_user_id' => null,
            'to_user_id' => $elena->id,
            'action' => 'assigned',
            'created_at' => now()->subHours(8),
        ]);
        PostItTransfer::create([
            'post_it_id' => $elenaDoing->id,
            'actor_id' => $elena->id,
            'from_user_id' => $elena->id,
            'to_user_id' => $elena->id,
            'action' => 'started',
            'created_at' => now()->subHours(3),
        ]);

        PostItComment::create([
            'post_it_id' => $elenaDoing->id,
            'user_id' => $elena->id,
            'body' => 'Webhook endpoints configured in Stripe Dashboard. Handling invoice.paid.',
            'created_at' => now()->subHours(2),
        ]);
        PostItComment::create([
            'post_it_id' => $elenaDoing->id,
            'user_id' => $sofia->id,
            'body' => 'Thanks Elena, please verify VAT receipt generation triggers correctly.',
            'created_at' => now()->subHour(),
        ]);

        // 5.2 assigned, URGENT, W3: Patch PACS viewer SSO session timeout bug
        $elenaAssignedUrgent = PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'requester_id' => $sofia->id,
            'facility_name' => 'Radiology Unit',
            'assignee_id' => $elena->id,
            'assigned_by_id' => $sofia->id,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Patch PACS viewer SSO session timeout bug',
            'description' => 'Active viewer tabs disconnect after 15 minutes of inactivity despite user interaction.',
            'status' => 'assigned',
            'priority' => 'urgent',
            'weight' => 3,
            'bounty_points' => 45,
            'started_at' => null,
            'completed_at' => null,
            'is_canceled' => false,
            'created_at' => now()->subHours(4),
        ]);

        PostItTransfer::create([
            'post_it_id' => $elenaAssignedUrgent->id,
            'actor_id' => $sofia->id,
            'from_user_id' => null,
            'to_user_id' => $elena->id,
            'action' => 'assigned',
            'created_at' => now()->subHours(4),
        ]);

        // 5.3 assigned, NORMAL, W2 (20 pts): Benchmark MySQL InnoDB index performance for tenant queries
        $elenaAssignedNormal = PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'requester_id' => $alex->id,
            'assignee_id' => $elena->id,
            'assigned_by_id' => $alex->id,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Benchmark MySQL InnoDB index performance for tenant queries',
            'description' => 'Analyze composite index selectivity on (tenant_id, department_id, status).',
            'status' => 'assigned',
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => 20,
            'started_at' => null,
            'completed_at' => null,
            'is_canceled' => false,
            'created_at' => now()->subHours(6),
        ]);

        PostItTransfer::create([
            'post_it_id' => $elenaAssignedNormal->id,
            'actor_id' => $alex->id,
            'from_user_id' => null,
            'to_user_id' => $elena->id,
            'action' => 'assigned',
            'created_at' => now()->subHours(6),
        ]);

        // --- Kostas K. (Infrastructure & Net) ---
        // 5.4 doing, NORMAL, W2 (20 pts): Configure Redis cache tags for tenant dashboard stats
        $kostasDoing = PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $infraDept->id,
            'requester_id' => $sofia->id,
            'facility_name' => 'Radiology Unit',
            'assignee_id' => $kostas->id,
            'assigned_by_id' => $sofia->id,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Configure Redis cache tags for tenant dashboard stats',
            'description' => 'Cache aggregate tenant metrics and invalidate upon Post-it completion events.',
            'status' => 'doing',
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => 20,
            'started_at' => now()->subMinutes(45),
            'completed_at' => null,
            'is_canceled' => false,
            'created_at' => now()->subHours(3),
        ]);

        PostItTransfer::create([
            'post_it_id' => $kostasDoing->id,
            'actor_id' => $sofia->id,
            'from_user_id' => null,
            'to_user_id' => $kostas->id,
            'action' => 'assigned',
            'created_at' => now()->subHours(3),
        ]);
        PostItTransfer::create([
            'post_it_id' => $kostasDoing->id,
            'actor_id' => $kostas->id,
            'from_user_id' => $kostas->id,
            'to_user_id' => $kostas->id,
            'action' => 'started',
            'created_at' => now()->subMinutes(45),
        ]);

        // 5.5 assigned, URGENT, W5 (75 pts): Refactor real-time WebSocket broadcasting events (Nick Pappas -> Elena D. -> Kostas K.)
        $kostasAssignedMultiHop = PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $infraDept->id,
            'requester_id' => $alex->id,
            'assignee_id' => $kostas->id,
            'assigned_by_id' => $elena->id,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Refactor real-time WebSocket broadcasting events',
            'description' => 'Migrate canvas drag actions to Laravel Reverb private channels for low-latency updates.',
            'status' => 'assigned',
            'priority' => 'urgent',
            'weight' => 5,
            'bounty_points' => 75,
            'started_at' => null,
            'completed_at' => null,
            'is_canceled' => false,
            'created_at' => now()->subDays(1),
        ]);

        PostItTransfer::create([
            'post_it_id' => $kostasAssignedMultiHop->id,
            'actor_id' => $alex->id,
            'from_user_id' => null,
            'to_user_id' => $nikos->id,
            'action' => 'assigned',
            'created_at' => now()->subDays(1),
        ]);
        PostItTransfer::create([
            'post_it_id' => $kostasAssignedMultiHop->id,
            'actor_id' => $nikos->id,
            'from_user_id' => $nikos->id,
            'to_user_id' => $elena->id,
            'action' => 'transferred',
            'created_at' => now()->subHours(12),
        ]);
        PostItTransfer::create([
            'post_it_id' => $kostasAssignedMultiHop->id,
            'actor_id' => $elena->id,
            'from_user_id' => $elena->id,
            'to_user_id' => $kostas->id,
            'action' => 'transferred',
            'created_at' => now()->subHours(2),
        ]);

        // --- Nick Pappas (Software) ---
        // 5.6 doing, HIGH, W2 (25 pts): Resolve MariaDB foreign key deadlock in concurrent sessions (Co-op: Kostas 30%)
        $nickDoing = PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'requester_id' => $alex->id,
            'assignee_id' => $nikos->id,
            'assigned_by_id' => $alex->id,
            'helper_id' => $kostas->id,
            'helper_split' => 30,
            'title' => 'Resolve MariaDB foreign key deadlock in concurrent sessions',
            'description' => 'Optimize transaction locks when multiple workers claim unassigned tickets simultaneously.',
            'status' => 'doing',
            'priority' => 'high',
            'weight' => 2,
            'bounty_points' => 25,
            'started_at' => now()->subHour(),
            'completed_at' => null,
            'is_canceled' => false,
            'created_at' => now()->subHours(3),
        ]);

        PostItTransfer::create([
            'post_it_id' => $nickDoing->id,
            'actor_id' => $alex->id,
            'from_user_id' => null,
            'to_user_id' => $nikos->id,
            'action' => 'assigned',
            'created_at' => now()->subHours(3),
        ]);
        PostItTransfer::create([
            'post_it_id' => $nickDoing->id,
            'actor_id' => $nikos->id,
            'from_user_id' => $nikos->id,
            'to_user_id' => $nikos->id,
            'action' => 'started',
            'created_at' => now()->subHour(),
        ]);

        // 5.7 assigned, NORMAL, W2 (20 pts): Implement PDF invoice export for EU VAT compliance (from Chris L., Central Lab)
        $nickAssigned = PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'requester_id' => $lab->id,
            'facility_name' => 'Central Lab',
            'assignee_id' => $nikos->id,
            'assigned_by_id' => $lab->id,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Implement PDF invoice export for EU VAT compliance',
            'description' => 'Generate downloadable PDF receipts containing Hellenic Tax and EU VAT reverse-charge notes.',
            'status' => 'assigned',
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => 20,
            'started_at' => null,
            'completed_at' => null,
            'is_canceled' => false,
            'created_at' => now()->subHours(2),
        ]);

        PostItTransfer::create([
            'post_it_id' => $nickAssigned->id,
            'actor_id' => $lab->id,
            'from_user_id' => null,
            'to_user_id' => $nikos->id,
            'action' => 'assigned',
            'created_at' => now()->subHours(2),
        ]);

        // --- Maria O. (Operations, Private column) ---
        // 5.8 doing, NORMAL, W2: Q4 vendor SLA compliance review
        $mariaDoing = PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $opsDept->id,
            'requester_id' => $maria->id,
            'assignee_id' => $maria->id,
            'assigned_by_id' => $maria->id,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Q4 vendor SLA compliance review',
            'description' => 'Review vendor uptime metrics and penalty thresholds for cloud hosting SLA.',
            'status' => 'doing',
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => 20,
            'started_at' => now()->subHours(2),
            'completed_at' => null,
            'is_canceled' => false,
            'created_at' => now()->subHours(4),
        ]);

        PostItTransfer::create([
            'post_it_id' => $mariaDoing->id,
            'actor_id' => $maria->id,
            'from_user_id' => null,
            'to_user_id' => $maria->id,
            'action' => 'assigned',
            'created_at' => now()->subHours(4),
        ]);
        PostItTransfer::create([
            'post_it_id' => $mariaDoing->id,
            'actor_id' => $maria->id,
            'from_user_id' => $maria->id,
            'to_user_id' => $maria->id,
            'action' => 'started',
            'created_at' => now()->subHours(2),
        ]);

        // 6. Seed Completed Column (status = 'done')
        // 6.1 Today (Completed normally): HIGH, W3 (38 pts, completed_at = now()->subHours(2), is_canceled = false)
        $doneToday = PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'requester_id' => $sofia->id,
            'facility_name' => 'Radiology Unit',
            'assignee_id' => $elena->id,
            'assigned_by_id' => $sofia->id,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Cross-browser WebSocket fallback for legacy mobile browsers',
            'description' => 'Architected reconnect loop, tested SSE fallback, and passed multi-department QA.',
            'status' => 'done',
            'priority' => 'high',
            'weight' => 3,
            'bounty_points' => 38,
            'started_at' => now()->subDays(1),
            'completed_at' => now()->subHours(2),
            'is_canceled' => false,
            'created_at' => now()->subDays(2),
        ]);

        PostItTransfer::create([
            'post_it_id' => $doneToday->id,
            'actor_id' => $sofia->id,
            'from_user_id' => null,
            'to_user_id' => $elena->id,
            'action' => 'assigned',
            'created_at' => now()->subDays(2),
        ]);
        PostItTransfer::create([
            'post_it_id' => $doneToday->id,
            'actor_id' => $elena->id,
            'from_user_id' => $elena->id,
            'to_user_id' => $elena->id,
            'action' => 'started',
            'created_at' => now()->subDays(1),
        ]);
        PostItTransfer::create([
            'post_it_id' => $doneToday->id,
            'actor_id' => $elena->id,
            'from_user_id' => $elena->id,
            'to_user_id' => $elena->id,
            'action' => 'completed',
            'created_at' => now()->subHours(2),
        ]);

        // 6.2 Today (Canceled / Self-Resolved with 0 pts): NORMAL, W2 (0 pts, completed_at = now()->subHour(), is_canceled = true)
        $canceledToday = PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $infraDept->id,
            'requester_id' => $sofia->id,
            'facility_name' => 'Radiology Unit',
            'assignee_id' => null,
            'assigned_by_id' => null,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Email client sync outage at reception desk',
            'description' => 'IMAP sync failing for front desk workstations after certificate renewal.',
            'status' => 'done',
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => 0,
            'started_at' => null,
            'completed_at' => now()->subHour(),
            'is_canceled' => true,
            'created_at' => now()->subHours(3),
        ]);

        PostItComment::create([
            'post_it_id' => $canceledToday->id,
            'user_id' => $sofia->id,
            'body' => '✅ Closed by requester: Local network switch recovered, issue resolved automatically.',
            'created_at' => now()->subHour(),
        ]);

        PostItTransfer::create([
            'post_it_id' => $canceledToday->id,
            'actor_id' => $sofia->id,
            'from_user_id' => null,
            'to_user_id' => null,
            'action' => 'closed_by_requester',
            'created_at' => now()->subHour(),
        ]);

        // 6.3 This Week (completed_at = now()->subDays(3)): HIGH, W4 (50 pts, is_canceled = false)
        $doneThisWeek = PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $infraDept->id,
            'requester_id' => $sofia->id,
            'facility_name' => 'Radiology Unit',
            'assignee_id' => $kostas->id,
            'assigned_by_id' => $sofia->id,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Replace rack UPS battery pack in main server room',
            'description' => 'Swapped degraded lithium battery cartridges in rack A4 and tested load switch.',
            'status' => 'done',
            'priority' => 'high',
            'weight' => 4,
            'bounty_points' => 50,
            'started_at' => now()->subDays(4),
            'completed_at' => now()->subDays(3),
            'is_canceled' => false,
            'created_at' => now()->subDays(5),
        ]);

        PostItTransfer::create([
            'post_it_id' => $doneThisWeek->id,
            'actor_id' => $sofia->id,
            'from_user_id' => null,
            'to_user_id' => $kostas->id,
            'action' => 'assigned',
            'created_at' => now()->subDays(5),
        ]);
        PostItTransfer::create([
            'post_it_id' => $doneThisWeek->id,
            'actor_id' => $kostas->id,
            'from_user_id' => $kostas->id,
            'to_user_id' => $kostas->id,
            'action' => 'started',
            'created_at' => now()->subDays(4),
        ]);
        PostItTransfer::create([
            'post_it_id' => $doneThisWeek->id,
            'actor_id' => $kostas->id,
            'from_user_id' => $kostas->id,
            'to_user_id' => $kostas->id,
            'action' => 'completed',
            'created_at' => now()->subDays(3),
        ]);

        // 6.4 Earlier / Archive (completed_at = now()->subDays(12)): NORMAL, W2 (20 pts, is_canceled = false)
        $doneEarlier = PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $softwareDept->id,
            'requester_id' => $lab->id,
            'facility_name' => 'Central Lab',
            'assignee_id' => $nikos->id,
            'assigned_by_id' => $lab->id,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => 'Provision workstation profiles for new lab staff',
            'description' => 'Configured Active Directory accounts, assigned security groups, and deployed certificates.',
            'status' => 'done',
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => 20,
            'started_at' => now()->subDays(13),
            'completed_at' => now()->subDays(12),
            'is_canceled' => false,
            'created_at' => now()->subDays(14),
        ]);

        PostItTransfer::create([
            'post_it_id' => $doneEarlier->id,
            'actor_id' => $lab->id,
            'from_user_id' => null,
            'to_user_id' => $nikos->id,
            'action' => 'assigned',
            'created_at' => now()->subDays(14),
        ]);
        PostItTransfer::create([
            'post_it_id' => $doneEarlier->id,
            'actor_id' => $nikos->id,
            'from_user_id' => $nikos->id,
            'to_user_id' => $nikos->id,
            'action' => 'started',
            'created_at' => now()->subDays(13),
        ]);
        PostItTransfer::create([
            'post_it_id' => $doneEarlier->id,
            'actor_id' => $nikos->id,
            'from_user_id' => $nikos->id,
            'to_user_id' => $nikos->id,
            'action' => 'completed',
            'created_at' => now()->subDays(12),
        ]);

        // 7. Synchronize collaborator current_capacity and exact specified total_points
        $userPointsMap = [
            $alex->id => 120,
            $nikos->id => 245,
            $elena->id => 380,
            $kostas->id => 310,
            $maria->id => 195,
            $giorgos->id => 90,
            $sofia->id => 0,
            $lab->id => 0,
        ];

        $users = [$alex, $nikos, $elena, $kostas, $maria, $giorgos, $sofia, $lab];
        foreach ($users as $user) {
            $user->update([
                'current_capacity' => (int) $user->assignedPostIts()->whereIn('status', ['assigned', 'doing'])->sum('weight'),
                'total_points' => $userPointsMap[$user->id] ?? 0,
            ]);
        }

        $this->info('Hellenic Demo Ops tenant successfully reset.');

        return self::SUCCESS;
    }
}
