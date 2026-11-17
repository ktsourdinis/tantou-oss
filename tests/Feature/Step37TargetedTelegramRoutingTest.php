<?php

namespace Tests\Feature;

use App\Events\BoardUpdated;
use App\Livewire\CapacityBoard;
use App\Livewire\TeamManagement;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class Step37TargetedTelegramRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Department $department;

    protected User $admin;

    protected User $worker;

    protected User $awayWorker;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('tantou.telegram.bot_token', '123456:TEST_STEP37_BOT_TOKEN');
        Config::set('tantou.telegram.bot_username', 'TantouStep37Bot');

        $this->tenant = Tenant::factory()->create([
            'name' => 'Targeted Routing Corp',
            'telegram_enabled' => true,
        ]);

        $this->department = Department::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Infrastructure & Cloud',
            'color' => '#3b82f6',
            'telegram_chat_id' => '-100999888777', // Department Group Chat
        ]);

        $this->admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Admin Alex',
            'email' => 'admin@step37.test',
            'visibility' => 'public',
            'is_available' => true,
        ]);

        $this->worker = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Kostas Worker',
            'email' => 'kostas@step37.test',
            'visibility' => 'public',
            'is_available' => true,
            'telegram_chat_id' => '777123456', // Personal Private Chat
            'telegram_notifications_enabled' => true,
        ]);

        $this->awayWorker = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Eleni Away',
            'email' => 'eleni@step37.test',
            'visibility' => 'public',
            'is_available' => false,
            'telegram_chat_id' => '888123456',
            'telegram_notifications_enabled' => true,
        ]);
    }

    public function test_user_visibility_updates_in_team_management_persist_correctly_to_users_visibility(): void
    {
        Event::fake([BoardUpdated::class]);

        $this->actingAs($this->admin);

        // 1. Edit via TeamManagement modal
        Livewire::test(TeamManagement::class)
            ->call('openEditUserModal', $this->worker->id)
            ->assertSet('userVisibility', 'public')
            ->set('userVisibility', 'internal')
            ->call('saveUser')
            ->assertHasNoErrors();

        $this->assertSame('internal', $this->worker->fresh()->visibility);
        $this->assertSame('internal', $this->worker->fresh()->board_visibility);

        // 2. Quick update via TeamManagement table dropdown
        Livewire::test(TeamManagement::class)
            ->call('quickUpdateVisibility', $this->worker->id, 'private')
            ->assertHasNoErrors();

        $this->assertSame('private', $this->worker->fresh()->visibility);
        $this->assertSame('private', $this->worker->fresh()->board_visibility);

        // 3. Worker column visibility change directly on the board saves permanently to users.visibility
        Livewire::test(CapacityBoard::class)
            ->call('updateVisibility', $this->worker->id, 'internal')
            ->assertHasNoErrors();

        $this->assertSame('internal', $this->worker->fresh()->visibility);
        $this->assertSame('internal', $this->worker->fresh()->board_visibility);

        // 4. Post-It assignment does not override or reset worker's established visibility state
        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->admin->id,
            'assignee_id' => $this->worker->id,
            'title' => 'Cluster Upgrade',
            'status' => 'assigned',
            'weight' => 2,
            'priority' => 'normal',
        ]);

        $this->assertSame('internal', $this->worker->fresh()->visibility);
    }

    public function test_direct_task_assignment_sends_telegram_alert_exclusively_to_assigned_worker_private_chat(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 301]], 200),
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->admin->id,
            'assignee_id' => $this->worker->id,
            'title' => 'Deploy Redis Sentinel Cluster',
            'status' => 'assigned',
            'weight' => 3,
            'priority' => 'high',
            'tracking_token' => 'TNT-REDIS-01',
        ]);

        $botService = app(TelegramBotService::class);
        $botService->routePostItNotification($postIt);

        // 1. Alert must be sent to the worker's personal Telegram chat
        Http::assertSent(function ($request) {
            $isToWorker = $request['chat_id'] === '777123456';
            $hasStartButton = false;
            $hasDoneButton = false;

            if (isset($request['reply_markup']['inline_keyboard'])) {
                foreach ($request['reply_markup']['inline_keyboard'] as $row) {
                    foreach ($row as $btn) {
                        if (str_starts_with($btn['callback_data'] ?? '', 'start:')) {
                            $hasStartButton = true;
                        }
                        if (str_starts_with($btn['callback_data'] ?? '', 'done:')) {
                            $hasDoneButton = true;
                        }
                    }
                }
            }

            return $isToWorker && $hasStartButton && $hasDoneButton;
        });

        // 2. Department group chat must NOT receive this direct assignment alert (no group spam)
        Http::assertNotSent(function ($request) {
            return $request['chat_id'] === '-100999888777';
        });
    }

    public function test_unassigned_backlog_tasks_notify_the_department_group_chat_with_claim_button(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 302]], 200),
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->admin->id,
            'assignee_id' => null, // Unassigned Backlog
            'title' => 'Emergency Firewall Rule Modification',
            'status' => 'backlog',
            'weight' => 2,
            'priority' => 'urgent',
            'tracking_token' => 'TNT-FW-99',
        ]);

        $botService = app(TelegramBotService::class);
        $botService->routePostItNotification($postIt);

        // 1. Alert must be sent to the department group chat
        Http::assertSent(function ($request) {
            $isToGroup = $request['chat_id'] === '-100999888777';
            $hasClaimButton = false;

            if (isset($request['reply_markup']['inline_keyboard'])) {
                foreach ($request['reply_markup']['inline_keyboard'] as $row) {
                    foreach ($row as $btn) {
                        if (str_starts_with($btn['callback_data'] ?? '', 'claim:')) {
                            $hasClaimButton = true;
                        }
                    }
                }
            }

            return $isToGroup && $hasClaimButton;
        });

        // 2. Worker's private chat must NOT receive an unassigned backlog alert
        Http::assertNotSent(function ($request) {
            return $request['chat_id'] === '777123456';
        });
    }

    public function test_assigned_task_never_notifies_department_group_even_if_assignee_is_away(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 303]], 200),
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->admin->id,
            'assignee_id' => $this->awayWorker->id, // Assigned to Away Worker
            'title' => 'SSL Certificate Expiration Handling',
            'status' => 'assigned',
            'weight' => 2,
            'priority' => 'high',
            'tracking_token' => 'TNT-SSL-01',
        ]);

        $botService = app(TelegramBotService::class);
        $botService->routePostItNotification($postIt);

        // Under Step 41 isolation: Department group chat must NEVER receive notification for assigned tasks!
        Http::assertNotSent(function ($request) {
            return $request['chat_id'] === '-100999888777';
        });

        // The assigned worker receives the notification directly
        Http::assertSent(function ($request) {
            return $request['chat_id'] === '888123456';
        });
    }

    public function test_capacity_board_creation_dispatches_targeted_routing(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 304]], 200),
        ]);

        $this->actingAs($this->admin);

        // 1. Direct assignment via CapacityBoard modal
        Livewire::test(CapacityBoard::class)
            ->set('title', 'Direct Assigned Task from Board')
            ->set('departmentId', $this->department->id)
            ->set('assigneeId', $this->worker->id)
            ->set('weight', 2)
            ->set('priority', 'normal')
            ->call('createPostIt')
            ->assertHasNoErrors();

        // Must notify worker private chat only
        Http::assertSent(function ($request) {
            return $request['chat_id'] === '777123456';
        });

        Http::assertNotSent(function ($request) {
            return $request['chat_id'] === '-100999888777';
        });
    }
}
