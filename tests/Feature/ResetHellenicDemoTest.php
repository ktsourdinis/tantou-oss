<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResetHellenicDemoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that tantou:reset-demo resets Hellenic Demo Ops with 100% English demo data.
     */
    public function test_reset_demo_command_creates_expected_data(): void
    {
        $this->artisan('tantou:reset-demo')
            ->assertSuccessful();

        // 1. Verify Tenant
        $tenant = Tenant::where('slug', 'hellenic-demo-ops')->first();
        $this->assertNotNull($tenant);
        $this->assertSame('Hellenic Demo Ops', $tenant->name);
        $this->assertTrue($tenant->public_submission_enabled);

        // 2. Verify 3 English Departments
        $this->assertDatabaseHas('departments', [
            'tenant_id' => $tenant->id,
            'name' => 'Software',
            'slug' => 'software',
        ]);
        $this->assertDatabaseHas('departments', [
            'tenant_id' => $tenant->id,
            'name' => 'Infrastructure & Net',
            'slug' => 'infrastructure-net',
        ]);
        $this->assertDatabaseHas('departments', [
            'tenant_id' => $tenant->id,
            'name' => 'Operations',
            'slug' => 'operations',
        ]);

        // 3. Verify Users and Capacity / Points
        $alex = User::where('email', 'admin@tantou.local')->first();
        $this->assertNotNull($alex);
        $this->assertSame('Alex M.', $alex->name);
        $this->assertSame('admin', $alex->role);
        $this->assertSame(120, $alex->total_points);

        $nikos = User::where('email', 'coordinator@tantou.local')->first();
        $this->assertNotNull($nikos);
        $this->assertSame('Nick Pappas', $nikos->name);
        $this->assertSame('coordinator', $nikos->role);
        $this->assertSame('public', $nikos->board_visibility);
        $this->assertTrue($nikos->is_available);
        $this->assertSame(10, $nikos->max_capacity);
        $this->assertSame(4, $nikos->current_capacity);
        $this->assertSame(245, $nikos->total_points);

        $elena = User::where('email', 'elena@tantou.local')->first();
        $this->assertNotNull($elena);
        $this->assertSame('Elena D.', $elena->name);
        $this->assertSame('worker', $elena->role);
        $this->assertSame('public', $elena->board_visibility);
        $this->assertTrue($elena->is_available);
        $this->assertSame(10, $elena->max_capacity);
        $this->assertSame(8, $elena->current_capacity);
        $this->assertSame(380, $elena->total_points);

        $kostas = User::where('email', 'kostas@tantou.local')->first();
        $this->assertNotNull($kostas);
        $this->assertSame('Kostas K.', $kostas->name);
        $this->assertSame('worker', $kostas->role);
        $this->assertSame('internal', $kostas->board_visibility);
        $this->assertTrue($kostas->is_available);
        $this->assertSame(10, $kostas->max_capacity);
        $this->assertSame(7, $kostas->current_capacity);
        $this->assertSame(310, $kostas->total_points);

        $maria = User::where('email', 'maria@tantou.local')->first();
        $this->assertNotNull($maria);
        $this->assertSame('Maria O.', $maria->name);
        $this->assertSame('worker', $maria->role);
        $this->assertSame('private', $maria->board_visibility);
        $this->assertTrue($maria->is_available);
        $this->assertSame(10, $maria->max_capacity);
        $this->assertSame(2, $maria->current_capacity);
        $this->assertSame(195, $maria->total_points);

        $giorgos = User::where('email', 'giorgos@tantou.local')->first();
        $this->assertNotNull($giorgos);
        $this->assertSame('George A.', $giorgos->name);
        $this->assertFalse($giorgos->is_available);
        $this->assertSame(90, $giorgos->total_points);

        $sofia = User::where('email', 'client@tantou.local')->first();
        $this->assertNotNull($sofia);
        $this->assertSame('Sofia C.', $sofia->name);
        $this->assertSame('client', $sofia->role);
        $this->assertSame('Radiology Unit', $sofia->facility_name);

        $lab = User::where('email', 'lab@tantou.local')->first();
        $this->assertNotNull($lab);
        $this->assertSame('Chris L.', $lab->name);
        $this->assertSame('client', $lab->role);
        $this->assertSame('Central Lab', $lab->facility_name);

        // 4. Verify Backlog Post-its
        $this->assertDatabaseHas('post_its', [
            'tenant_id' => $tenant->id,
            'title' => 'Security audit for multi-tenant data isolation',
            'status' => 'backlog',
            'priority' => 'urgent',
            'weight' => 5,
        ]);

        $this->assertDatabaseHas('post_its', [
            'tenant_id' => $tenant->id,
            'title' => 'DICOM printer offline in imaging room 2',
            'guest_name' => 'Dr. Markos',
            'facility_name' => 'Radiology Unit',
            'guest_contact' => 'Ext. 2140',
            'tracking_token' => 'TNT-DEMO2026',
            'status' => 'backlog',
            'priority' => 'high',
            'weight' => 2,
        ]);

        // 5. Verify Active Collaborator Queues
        // Elena D.
        $this->assertDatabaseHas('post_its', [
            'tenant_id' => $tenant->id,
            'assignee_id' => $elena->id,
            'title' => 'Integrate Stripe billing webhooks for annual subscriptions',
            'status' => 'doing',
            'priority' => 'high',
            'weight' => 3,
            'bounty_points' => 41,
        ]);
        $stripeTask = PostIt::where('title', 'Integrate Stripe billing webhooks for annual subscriptions')->first();
        $this->assertCount(2, $stripeTask->comments);

        $this->assertDatabaseHas('post_its', [
            'tenant_id' => $tenant->id,
            'assignee_id' => $elena->id,
            'title' => 'Patch PACS viewer SSO session timeout bug',
            'status' => 'assigned',
            'priority' => 'urgent',
            'weight' => 3,
        ]);

        $this->assertDatabaseHas('post_its', [
            'tenant_id' => $tenant->id,
            'assignee_id' => $elena->id,
            'title' => 'Benchmark MySQL InnoDB index performance for tenant queries',
            'status' => 'assigned',
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => 20,
        ]);

        // Kostas K.
        $this->assertDatabaseHas('post_its', [
            'tenant_id' => $tenant->id,
            'assignee_id' => $kostas->id,
            'title' => 'Configure Redis cache tags for tenant dashboard stats',
            'status' => 'doing',
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => 20,
        ]);

        $wsTask = PostIt::where('title', 'Refactor real-time WebSocket broadcasting events')->first();
        $this->assertNotNull($wsTask);
        $this->assertSame('assigned', $wsTask->status);
        $this->assertSame(5, $wsTask->weight);
        $this->assertSame(75, $wsTask->bounty_points);
        $this->assertCount(3, $wsTask->transfers);

        // Nick Pappas
        $dbDeadlock = PostIt::where('title', 'Resolve MariaDB foreign key deadlock in concurrent sessions')->first();
        $this->assertNotNull($dbDeadlock);
        $this->assertSame('doing', $dbDeadlock->status);
        $this->assertSame($kostas->id, $dbDeadlock->helper_id);
        $this->assertSame(30, $dbDeadlock->helper_split);
        $this->assertSame(25, $dbDeadlock->bounty_points);

        $this->assertDatabaseHas('post_its', [
            'tenant_id' => $tenant->id,
            'assignee_id' => $nikos->id,
            'title' => 'Implement PDF invoice export for EU VAT compliance',
            'status' => 'assigned',
            'facility_name' => 'Central Lab',
        ]);

        // Maria O.
        $this->assertDatabaseHas('post_its', [
            'tenant_id' => $tenant->id,
            'assignee_id' => $maria->id,
            'title' => 'Q4 vendor SLA compliance review',
            'status' => 'doing',
            'weight' => 2,
        ]);

        // 6. Verify Completed Column
        $this->assertDatabaseHas('post_its', [
            'tenant_id' => $tenant->id,
            'title' => 'Cross-browser WebSocket fallback for legacy mobile browsers',
            'status' => 'done',
            'is_canceled' => false,
            'bounty_points' => 38,
        ]);

        $canceledTask = PostIt::where('title', 'Email client sync outage at reception desk')->first();
        $this->assertNotNull($canceledTask);
        $this->assertSame('done', $canceledTask->status);
        $this->assertTrue($canceledTask->is_canceled);
        $this->assertSame(0, $canceledTask->bounty_points);
        $this->assertStringContainsString('Closed by requester', $canceledTask->comments->first()->body);

        $this->assertDatabaseHas('post_its', [
            'tenant_id' => $tenant->id,
            'title' => 'Replace rack UPS battery pack in main server room',
            'status' => 'done',
            'is_canceled' => false,
            'bounty_points' => 50,
        ]);

        $this->assertDatabaseHas('post_its', [
            'tenant_id' => $tenant->id,
            'title' => 'Provision workstation profiles for new lab staff',
            'status' => 'done',
            'is_canceled' => false,
            'bounty_points' => 20,
        ]);
    }

    /**
     * Test that tantou:reset-demo does not affect other tenants.
     */
    public function test_reset_demo_preserves_other_tenants(): void
    {
        $otherTenant = Tenant::factory()->create([
            'slug' => 'acme-corp',
            'name' => 'Acme Corporation',
        ]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email' => 'user@acme.local',
            'name' => 'Acme Employee',
        ]);
        $otherPostIt = PostIt::factory()->create([
            'tenant_id' => $otherTenant->id,
            'requester_id' => $otherUser->id,
            'title' => 'Acme Private Internal Task',
        ]);

        $this->artisan('tantou:reset-demo')
            ->assertSuccessful();

        $this->assertDatabaseHas('tenants', ['id' => $otherTenant->id, 'slug' => 'acme-corp']);
        $this->assertDatabaseHas('users', ['id' => $otherUser->id, 'email' => 'user@acme.local']);
        $this->assertDatabaseHas('post_its', ['id' => $otherPostIt->id, 'title' => 'Acme Private Internal Task']);
    }

    /**
     * Test Quick Demo Login cards display on login page.
     */
    public function test_quick_demo_login_cards_displayed(): void
    {
        $this->artisan('tantou:reset-demo');

        Livewire::test(Login::class)
            ->assertSee('Alex M. (Admin)')
            ->assertSee('Nick Pappas (Coordinator)')
            ->assertSee('Elena D. (Worker - Public)')
            ->assertSee('Kostas K. (Worker - Internal)')
            ->assertSee('Sofia C. (Client - Radiology Unit)');
    }
}
