<?php

namespace Tests\Feature;

use App\Events\BoardUpdated;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TelegramBotService;
use App\Services\TelegramUpdateProcessor;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramInlineButtonsAndTrackerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Edition::fake(false);

        Config::set('tantou.telegram.bot_token', '123456:TEST_BOT_TOKEN');
        Config::set('tantou.telegram.bot_username', 'TantouTestBot');
    }

    protected function tearDown(): void
    {
        Edition::fake(null);
        parent::tearDown();
    }

    /**
     * Test GET /track/{token} passes through IdentifyTenant cleanly with status 200
     * and without throwing Class 'App\Http\Middleware\PostIt' not found.
     */
    public function test_track_token_endpoint_resolves_tenant_and_returns_200_without_middleware_error(): void
    {
        $tenant = Tenant::create([
            'name' => 'Acme Logistics',
            'slug' => 'acme-logistics',
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'title' => 'Server Maintenance & Disk Migration',
            'status' => 'assigned',
            'weight' => 3,
            'priority' => 'high',
            'bounty_points' => 30,
            'guest_name' => 'John Customer',
            'guest_email' => 'john@customer.test',
            'tracking_token' => 'TRACK-TEST-ABC-999',
        ]);

        $response = $this->get('/track/'.$postIt->tracking_token);

        $response->assertStatus(200);
        $response->assertSee('Server Maintenance & Disk Migration');
        $response->assertSee('#TRACK-TEST-ABC-999');
    }

    /**
     * Test Telegram getUpdates explicitly passes allowed_updates with callback_query.
     */
    public function test_telegram_get_updates_includes_callback_query_in_allowed_updates(): void
    {
        Http::fake([
            'https://api.telegram.org/*/getUpdates' => Http::response([
                'ok' => true,
                'result' => [],
            ], 200),
        ]);

        $tenant = Tenant::create([
            'name' => 'Acme Logistics',
            'slug' => 'acme-logistics',
            'telegram_enabled' => true,
        ]);

        $bot = app(TelegramBotService::class);
        $updates = $bot->getUpdates($tenant, 0, 100, 0);

        $this->assertIsArray($updates);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/getUpdates')
                && isset($request['allowed_updates'])
                && str_contains((string) $request['allowed_updates'], 'callback_query')
                && str_contains((string) $request['allowed_updates'], 'message');
        });
    }

    /**
     * Test full callback_query sequence:
     * 1. start:{tenantId}:{postItId} transitions PostIt from assigned to doing and sets started_at.
     * 2. done:{tenantId}:{postItId} transitions PostIt from doing to done (is_canceled = false), sets completed_at, and increments total_points.
     */
    public function test_telegram_callback_query_start_and_complete_flow_updates_database_and_bounty(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200),
        ]);

        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::create([
            'name' => 'DevOps Central',
            'slug' => 'devops-central',
            'telegram_enabled' => true,
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Dimitris Tech',
            'email' => 'dimitris@devops.test',
            'password' => bcrypt('Secret123!@#'),
            'role' => 'worker',
            'telegram_chat_id' => '777888999',
            'telegram_notifications_enabled' => true,
            'total_points' => 100,
            'current_capacity' => 0,
            'max_capacity' => 10,
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'assignee_id' => $user->id,
            'assigned_by_id' => $user->id,
            'title' => 'Optimize Redis Caching Layer',
            'status' => 'assigned',
            'weight' => 3,
            'priority' => 'urgent',
            'bounty_points' => 50,
            'tracking_token' => 'TRACK-REDIS-101',
        ]);

        $processor = app(TelegramUpdateProcessor::class);

        // 1. Simulating callback_query for 'start'
        $startUpdate = [
            'update_id' => 80001,
            'callback_query' => [
                'id' => 'cb_query_start_1',
                'from' => [
                    'id' => 777888999,
                    'is_bot' => false,
                    'first_name' => 'Dimitris',
                ],
                'message' => [
                    'message_id' => 120,
                    'chat' => ['id' => 777888999],
                ],
                'data' => "start:{$tenant->id}:{$postIt->id}",
            ],
        ];

        $processor->processUpdate($startUpdate);

        $postIt->refresh();
        $this->assertEquals('doing', $postIt->status);
        $this->assertNotNull($postIt->started_at);

        Event::assertDispatched(BoardUpdated::class, function ($event) use ($tenant, $user) {
            return $event->tenantId === $tenant->id && $event->actorUserId === $user->id;
        });

        // 2. Simulating callback_query for 'done'
        $doneUpdate = [
            'update_id' => 80002,
            'callback_query' => [
                'id' => 'cb_query_done_1',
                'from' => [
                    'id' => 777888999,
                    'is_bot' => false,
                    'first_name' => 'Dimitris',
                ],
                'message' => [
                    'message_id' => 120,
                    'chat' => ['id' => 777888999],
                ],
                'data' => "done:{$tenant->id}:{$postIt->id}",
            ],
        ];

        $processor->processUpdate($doneUpdate);

        $postIt->refresh();
        $this->assertEquals('done', $postIt->status);
        $this->assertFalse((bool) $postIt->is_canceled);
        $this->assertNotNull($postIt->completed_at);

        $user->refresh();
        // 100 starting points + 50 bounty points = 150
        $this->assertGreaterThanOrEqual(150, $user->total_points);

        Event::assertDispatched(BoardUpdated::class, function ($event) use ($tenant, $user) {
            return $event->tenantId === $tenant->id && $event->actorUserId === $user->id;
        });

        // Verify Telegram message markup was edited to show completed
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/editMessageReplyMarkup')
                && str_contains(json_encode($request['reply_markup']), 'Completed by Dimitris Tech');
        });
    }

    /**
     * Test claim callback query assigns card and dispatches board updated.
     */
    public function test_telegram_callback_query_claim_flow_assigns_card(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200),
        ]);

        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::create([
            'name' => 'Cloud Hosters',
            'slug' => 'cloud-hosters',
            'telegram_enabled' => true,
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Elena Support',
            'email' => 'elena@cloud.test',
            'password' => bcrypt('Secret123!@#'),
            'role' => 'worker',
            'telegram_chat_id' => '444333222',
            'telegram_notifications_enabled' => true,
            'total_points' => 0,
            'current_capacity' => 0,
            'max_capacity' => 10,
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'assignee_id' => null,
            'title' => 'Incoming Customer Ticket',
            'status' => 'backlog',
            'weight' => 2,
            'priority' => 'normal',
            'bounty_points' => 20,
            'tracking_token' => 'TRACK-CLAIM-001',
        ]);

        $processor = app(TelegramUpdateProcessor::class);

        $claimUpdate = [
            'update_id' => 80003,
            'callback_query' => [
                'id' => 'cb_query_claim_1',
                'from' => [
                    'id' => 444333222,
                    'is_bot' => false,
                    'first_name' => 'Elena',
                ],
                'message' => [
                    'message_id' => 150,
                    'chat' => ['id' => 444333222],
                ],
                'data' => "claim:{$tenant->id}:{$postIt->id}",
            ],
        ];

        $processor->processUpdate($claimUpdate);

        $postIt->refresh();
        $this->assertEquals('assigned', $postIt->status);
        $this->assertEquals($user->id, $postIt->assignee_id);

        $user->refresh();
        $this->assertEquals(2, $user->current_capacity);

        Event::assertDispatched(BoardUpdated::class);
    }
}
