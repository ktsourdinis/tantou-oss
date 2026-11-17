<?php

namespace Tests\Feature;

use App\Livewire\TeamManagement;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class Step41RosterRenderingAndTelegramIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Department $department;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'name' => 'Acme Operations',
            'slug' => 'acme-ops',
            'telegram_enabled' => true,
            'telegram_bot_token' => 'mock-token-12345',
        ]);

        $this->department = Department::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Engineering',
            'slug' => 'engineering',
            'color' => '#3b82f6',
        ]);

        $this->admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Alex Admin',
            'email' => 'alex@acme.gr',
        ]);
    }

    /**
     * 1. Creating a new member in TeamManagement immediately renders their full name,
     * email, and role in the roster table without an empty row.
     */
    public function test_creating_a_new_member_in_team_management_immediately_renders_in_roster(): void
    {
        Livewire::actingAs($this->admin)
            ->test(TeamManagement::class)
            ->call('setActiveTab', 'roster')
            ->call('openCreateUserModal')
            ->set('userName', 'Dimitris Developer')
            ->set('userEmail', 'dimitris@acme.gr')
            ->set('userRole', 'worker')
            ->set('userDepartmentId', $this->department->id)
            ->set('userPassword', 'SecurePass123!#')
            ->set('userMaxCapacity', 14)
            ->call('saveUser')
            ->assertHasNoErrors()
            ->assertSet('showUserModal', false)
            ->assertSee('Dimitris Developer')
            ->assertSee('dimitris@acme.gr')
            ->assertSee('worker')
            ->assertSee('Engineering');

        $newUser = User::where('email', 'dimitris@acme.gr')->first();
        $this->assertNotNull($newUser);
        $this->assertEquals('Dimitris Developer', $newUser->name);
        $this->assertEquals(14, $newUser->max_capacity);
    }

    /**
     * 2. When a task is assigned to Worker B in Department X, Worker A (or the Department X channel)
     * does NOT receive a Telegram notification—only Worker B receives it.
     */
    public function test_assigned_task_notifies_only_assignee_and_never_department_channel_or_other_workers(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 101]], 200),
        ]);

        // Link department to a group chat
        $this->department->update([
            'telegram_chat_id' => '-100999888777',
        ]);

        // Worker A (Colleague in same department)
        $workerA = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Worker Alpha',
            'telegram_chat_id' => '111111',
            'telegram_notifications_enabled' => true,
        ]);

        // Worker B (Assigned worker)
        $workerB = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Worker Beta',
            'telegram_chat_id' => '222222',
            'telegram_notifications_enabled' => true,
        ]);

        // Task assigned to Worker B
        $postIt = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'assignee_id' => $workerB->id,
            'status' => 'assigned',
            'title' => 'Server Maintenance',
        ]);

        $service = app(TelegramBotService::class);
        $service->routePostItNotification($postIt, 'assigned');

        // Verify that Telegram sendMessage was called for Worker B (222222)
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && $request['chat_id'] === '222222';
        });

        // Verify that Telegram sendMessage was NEVER called for Department Group (-100999888777)
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && $request['chat_id'] === '-100999888777';
        });

        // Verify that Telegram sendMessage was NEVER called for Worker A (111111)
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && $request['chat_id'] === '111111';
        });
    }

    /**
     * 3. When an unassigned task is submitted to Department X and Department X has no group telegram_chat_id,
     * available workers in Department X with personal telegram_chat_id receive the [ 🙋‍♂️ Claim ] alert directly.
     */
    public function test_unassigned_backlog_task_with_zero_group_routes_to_available_workers_in_department(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 202]], 200),
        ]);

        // Ensure department has NO group chat linked
        $this->department->update([
            'telegram_chat_id' => null,
        ]);

        // Worker 1: Available with Telegram linked
        $worker1 = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'is_available' => true,
            'telegram_chat_id' => '333333',
            'telegram_notifications_enabled' => true,
        ]);

        // Worker 2: Available with Telegram linked
        $worker2 = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'is_available' => true,
            'telegram_chat_id' => '444444',
            'telegram_notifications_enabled' => true,
        ]);

        // Worker 3: AWAY / Unavailable (should NOT receive)
        $worker3 = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'is_available' => false,
            'telegram_chat_id' => '555555',
            'telegram_notifications_enabled' => true,
        ]);

        // Worker 4: Different department (should NOT receive)
        $otherDept = Department::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Support',
        ]);
        $workerOther = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $otherDept->id,
            'is_available' => true,
            'telegram_chat_id' => '666666',
            'telegram_notifications_enabled' => true,
        ]);

        // Unassigned Post-It in Engineering
        $postIt = PostIt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'assignee_id' => null,
            'status' => 'backlog',
            'title' => 'Critical Pipeline Failure',
        ]);

        $service = app(TelegramBotService::class);
        $service->routePostItNotification($postIt);

        // Worker 1 and Worker 2 must receive the claim alert
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && $request['chat_id'] === '333333';
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && $request['chat_id'] === '444444';
        });

        // Worker 3 (Away) must NOT receive
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && $request['chat_id'] === '555555';
        });

        // Worker Other (Different department) must NOT receive
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && $request['chat_id'] === '666666';
        });
    }

    /**
     * 4. Admin can unlink department Telegram chat with 1 click.
     */
    public function test_admin_can_unlink_department_telegram_group(): void
    {
        $this->department->update([
            'telegram_chat_id' => '-100555444333',
            'telegram_link_token' => 'some-token',
        ]);

        Livewire::actingAs($this->admin)
            ->test(TeamManagement::class)
            ->call('unlinkDepartmentTelegram', $this->department->id);

        $this->department->refresh();
        $this->assertNull($this->department->telegram_chat_id);
        $this->assertNull($this->department->telegram_link_token);
    }
}
