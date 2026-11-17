<?php

namespace Tests\Feature;

use App\Livewire\CapacityBoard;
use App\Livewire\TeamManagement;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TelegramBotService;
use App\Services\TelegramUpdateProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TelegramBotIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('tantou.telegram.bot_token', '123456:TEST_GLOBAL_BOT_TOKEN');
        Config::set('tantou.telegram.bot_username', 'TantouTestBot');
        Config::set('tantou.telegram.webhook_secret', 'test-global-secret-key-64-characters-long-1234567890abcdef1234567890');
    }

    /**
     * Test pairing via /start usr_{token} binds telegram_chat_id strictly to that user.
     */
    public function test_user_pairing_via_start_command_binds_telegram_chat_id_and_clears_token(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 101]], 200),
        ]);

        $tenant = Tenant::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'telegram_enabled' => true,
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Alice Worker',
            'email' => 'alice@acme.test',
            'password' => bcrypt('Password123!@#'),
            'role' => 'worker',
            'telegram_link_token' => 'token_alice_12345',
            'telegram_chat_id' => null,
            'telegram_username' => null,
        ]);

        $update = [
            'update_id' => 10001,
            'message' => [
                'message_id' => 1,
                'chat' => [
                    'id' => 888777,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 888777,
                    'is_bot' => false,
                    'first_name' => 'Alice',
                    'username' => 'alice_on_telegram',
                ],
                'text' => '/start usr_token_alice_12345',
            ],
        ];

        $processor = app(TelegramUpdateProcessor::class);
        $result = $processor->processUpdate($update);

        $this->assertTrue($result);

        $user->refresh();
        $this->assertEquals('888777', $user->telegram_chat_id);
        $this->assertEquals('alice_on_telegram', $user->telegram_username);
        $this->assertNull($user->telegram_link_token);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendMessage') &&
                str_contains($request['text'], 'Alice Worker') &&
                str_contains($request['text'], 'Acme Corp');
        });
    }

    /**
     * Test department group linking via /link dept_{token} in a group chat.
     */
    public function test_department_group_linking_via_link_command_binds_chat_id(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 102]], 200),
        ]);

        $tenant = Tenant::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'telegram_enabled' => true,
        ]);

        $department = Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'Infrastructure',
            'slug' => 'infrastructure',
            'color' => '#6366f1',
            'telegram_link_token' => 'dept_token_infra_999',
            'telegram_chat_id' => null,
        ]);

        $update = [
            'update_id' => 10002,
            'message' => [
                'message_id' => 2,
                'chat' => [
                    'id' => -100987654321,
                    'type' => 'supergroup',
                    'title' => 'Infra Ops Group',
                ],
                'from' => [
                    'id' => 888777,
                    'is_bot' => false,
                    'first_name' => 'Alice',
                ],
                'text' => '/link dept_dept_token_infra_999',
            ],
        ];

        $processor = app(TelegramUpdateProcessor::class);
        $result = $processor->processUpdate($update);

        $this->assertTrue($result);

        $department->refresh();
        $this->assertEquals('-100987654321', $department->telegram_chat_id);
        $this->assertNull($department->telegram_link_token);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendMessage') &&
                str_contains($request['text'], 'Infrastructure') &&
                str_contains($request['text'], 'Acme Corp');
        });
    }

    /**
     * Test that clicking start and done via callback queries transitions card when in the same tenant.
     */
    public function test_collaborator_can_start_and_complete_task_via_callback_query(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200),
        ]);

        $tenant = Tenant::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'telegram_enabled' => true,
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Bob Dev',
            'email' => 'bob@acme.test',
            'password' => bcrypt('Password123!@#'),
            'role' => 'worker',
            'telegram_chat_id' => '555444',
            'telegram_notifications_enabled' => true,
            'total_points' => 10,
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'requester_id' => $user->id,
            'assignee_id' => $user->id,
            'title' => 'Fix Database Index',
            'status' => 'assigned',
            'weight' => 2,
            'priority' => 'normal',
            'bounty_points' => 30,
            'tracking_token' => 'TNT-1001',
        ]);

        $processor = app(TelegramUpdateProcessor::class);

        // 1. Action: start
        $startUpdate = [
            'update_id' => 10003,
            'callback_query' => [
                'id' => 'cb_query_1',
                'from' => [
                    'id' => 555444,
                    'is_bot' => false,
                    'first_name' => 'Bob',
                ],
                'message' => [
                    'message_id' => 50,
                    'chat' => ['id' => 555444],
                ],
                'data' => "start:{$tenant->id}:{$postIt->id}",
            ],
        ];

        $processor->processUpdate($startUpdate);

        $postIt->refresh();
        $this->assertEquals('doing', $postIt->status);
        $this->assertNotNull($postIt->started_at);

        // 2. Action: done
        $doneUpdate = [
            'update_id' => 10004,
            'callback_query' => [
                'id' => 'cb_query_2',
                'from' => [
                    'id' => 555444,
                    'is_bot' => false,
                    'first_name' => 'Bob',
                ],
                'message' => [
                    'message_id' => 50,
                    'chat' => ['id' => 555444],
                ],
                'data' => "done:{$tenant->id}:{$postIt->id}",
            ],
        ];

        $processor->processUpdate($doneUpdate);

        $postIt->refresh();
        $this->assertEquals('done', $postIt->status);
        $this->assertNotNull($postIt->completed_at);

        $user->refresh();
        $this->assertGreaterThanOrEqual(40, $user->total_points);
    }

    /**
     * Test Strict Multi-Tenant Isolation: User from Tenant B cannot interact with Tenant A's tasks.
     */
    public function test_cross_tenant_isolation_rejects_foreign_user_callback_actions(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200),
        ]);

        $tenantA = Tenant::create(['name' => 'Tenant Alpha', 'slug' => 'tenant-alpha', 'telegram_enabled' => true]);
        $tenantB = Tenant::create(['name' => 'Tenant Beta', 'slug' => 'tenant-beta', 'telegram_enabled' => true]);

        $userA = User::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Alpha Worker',
            'email' => 'worker@alpha.test',
            'password' => bcrypt('Password123!@#'),
            'role' => 'worker',
            'telegram_chat_id' => '111111',
        ]);

        $userB = User::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Beta Worker',
            'email' => 'worker@beta.test',
            'password' => bcrypt('Password123!@#'),
            'role' => 'worker',
            'telegram_chat_id' => '222222',
        ]);

        $postItA = PostIt::create([
            'tenant_id' => $tenantA->id,
            'requester_id' => $userA->id,
            'assignee_id' => $userA->id,
            'title' => 'Alpha Confidential Security Task',
            'status' => 'assigned',
            'weight' => 3,
            'priority' => 'urgent',
            'tracking_token' => 'TNT-ALPHA-01',
        ]);

        $processor = app(TelegramUpdateProcessor::class);

        // User B attempts to trigger 'start' or 'done' on Tenant A's task
        $spoofedUpdate = [
            'update_id' => 10005,
            'callback_query' => [
                'id' => 'cb_query_spoof',
                'from' => [
                    'id' => 222222, // Belongs to Tenant B only
                    'is_bot' => false,
                    'first_name' => 'Attacker',
                ],
                'message' => [
                    'message_id' => 99,
                    'chat' => ['id' => 222222],
                ],
                'data' => "start:{$tenantA->id}:{$postItA->id}",
            ],
        ];

        $processor->processUpdate($spoofedUpdate);

        // Task in Tenant A MUST NOT have changed
        $postItA->refresh();
        $this->assertEquals('assigned', $postItA->status);
        $this->assertNull($postItA->started_at);

        // Telegram answerCallbackQuery must have been called with authorization error
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/answerCallbackQuery') &&
                str_contains($request['text'], 'Unauthorized');
        });
    }

    /**
     * Test that when telegram_privacy_mode is enabled, outgoing notifications redact titles and descriptions.
     */
    public function test_privacy_mode_redacts_task_titles_and_descriptions_in_telegram_messages(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 201]], 200),
        ]);

        $tenant = Tenant::create([
            'name' => 'Healthcare Trust',
            'slug' => 'healthcare-trust',
            'telegram_enabled' => true,
            'telegram_privacy_mode' => true, // GDPR Privacy Mode ON
        ]);

        $department = Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cardiology',
            'slug' => 'cardiology',
            'color' => '#e11d48',
            'telegram_chat_id' => '-100555666777',
        ]);

        $worker = User::create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'name' => 'Dr. Smith',
            'email' => 'smith@hospital.test',
            'password' => bcrypt('Password123!@#'),
            'role' => 'worker',
            'telegram_chat_id' => '333999',
            'telegram_notifications_enabled' => true,
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'requester_id' => $worker->id,
            'assignee_id' => $worker->id,
            'facility_name' => 'Saint Luke Clinic',
            'title' => 'Patient Medical Record Leak Investigation',
            'description' => 'Extremely sensitive patient diagnostic details here',
            'status' => 'assigned',
            'weight' => 4,
            'priority' => 'urgent',
            'tracking_token' => 'TNT-7890',
        ]);

        $service = app(TelegramBotService::class);
        $service->notifyTaskAssigned($postIt);

        Http::assertSent(function ($request) {
            $payload = $request['text'];

            // Must NOT contain sensitive titles or descriptions
            $hasSensitiveTitle = str_contains($payload, 'Patient Medical Record Leak Investigation');
            $hasSensitiveDesc = str_contains($payload, 'Extremely sensitive patient diagnostic details');

            // Must contain privacy token and metadata
            $hasToken = str_contains($payload, '#TNT-7890');
            $hasFacility = str_contains($payload, 'Saint Luke Clinic');
            $hasPriority = str_contains($payload, 'URGENT');
            $hasWeight = str_contains($payload, 'W4');
            $hasProtectedLabel = str_contains($payload, 'Protected Task');

            return ! $hasSensitiveTitle &&
                ! $hasSensitiveDesc &&
                $hasToken &&
                $hasFacility &&
                $hasPriority &&
                $hasWeight &&
                $hasProtectedLabel;
        });
    }

    /**
     * Test Webhook endpoint validates X-Telegram-Bot-Api-Secret-Token correctly.
     */
    public function test_webhook_endpoint_authenticates_secret_token(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200),
        ]);

        $secret = 'test-global-secret-key-64-characters-long-1234567890abcdef1234567890';
        $update = [
            'update_id' => 99999,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => 12345, 'type' => 'private'],
                'from' => ['id' => 12345, 'is_bot' => false],
                'text' => '/status',
            ],
        ];

        // 1. Without secret token header -> 403
        $this->postJson('/api/telegram/webhook', $update)
            ->assertStatus(403);

        // 2. With invalid secret token header -> 403
        $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret'])
            ->postJson('/api/telegram/webhook', $update)
            ->assertStatus(403);

        // 3. With matching global secret token header -> 200
        $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => $secret])
            ->postJson('/api/telegram/webhook', $update)
            ->assertStatus(200)
            ->assertJson(['ok' => true]);

        // 4. Tenant-scoped webhook route
        $tenant = Tenant::create([
            'name' => 'Custom Webhook Tenant',
            'slug' => 'custom-webhook',
            'telegram_enabled' => true,
            'telegram_webhook_secret' => 'custom-tenant-secret-key-1234567890abcdef',
        ]);

        $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'custom-tenant-secret-key-1234567890abcdef'])
            ->postJson('/api/telegram/webhook/custom-webhook', $update)
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    }

    /**
     * Test polling command processes updates and updates offset.
     */
    public function test_polling_command_processes_updates_and_advances_offset(): void
    {
        Http::fake([
            'https://api.telegram.org/*/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 77701,
                        'message' => [
                            'message_id' => 10,
                            'chat' => ['id' => 12345, 'type' => 'private'],
                            'from' => ['id' => 12345, 'is_bot' => false],
                            'text' => '/status',
                        ],
                    ],
                ],
            ], 200),
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200),
        ]);

        $this->artisan('tantou:telegram-poll', ['--once' => true])
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/getUpdates');
        });
    }

    /**
     * Test Livewire ManagesTelegramPairing trait methods.
     */
    public function test_livewire_telegram_pairing_modal_lifecycle(): void
    {
        $tenant = Tenant::create([
            'name' => 'Demo Org',
            'slug' => 'demo-org',
            'telegram_enabled' => true,
        ]);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Alex',
            'email' => 'admin@demo.test',
            'password' => bcrypt('Password123!@#'),
            'role' => 'admin',
            'telegram_chat_id' => '123456',
            'telegram_username' => 'alex_admin',
            'telegram_notifications_enabled' => true,
        ]);

        $department = Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'Support Desk',
            'slug' => 'support-desk',
            'color' => '#10b981',
        ]);

        $this->actingAs($admin);

        // Test unlink and notification toggle in CapacityBoard
        Livewire::test(CapacityBoard::class)
            ->call('openTelegramModal')
            ->assertSet('showTelegramModal', true)
            ->call('toggleTelegramNotifications')
            ->assertSet('showTelegramModal', true);

        $admin->refresh();
        $this->assertFalse($admin->telegram_notifications_enabled);

        Livewire::test(CapacityBoard::class)
            ->call('unlinkTelegram');

        $admin->refresh();
        $this->assertNull($admin->telegram_chat_id);
        $this->assertNull($admin->telegram_username);

        // Test department Telegram link token generation in TeamManagement
        $teamTest = Livewire::test(TeamManagement::class)
            ->call('generateDepartmentTelegramToken', $department->id)
            ->assertSet('selectedDepartmentTelegramId', $department->id);
        $this->assertNotNull($teamTest->get('departmentTelegramToken'));

        $department->refresh();
        $this->assertNotNull($department->telegram_link_token);

        // Unlink department
        $department->update(['telegram_chat_id' => '-100999888']);
        Livewire::test(TeamManagement::class)
            ->call('unlinkDepartmentTelegram', $department->id);

        $department->refresh();
        $this->assertNull($department->telegram_chat_id);
    }
}
