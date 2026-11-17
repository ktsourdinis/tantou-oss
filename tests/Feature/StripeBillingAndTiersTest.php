<?php

namespace Tests\Feature;

use App\Livewire\CapacityBoard;
use App\Livewire\TeamManagement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeBillingService;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class StripeBillingAndTiersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Edition::fake(true);
    }

    protected function tearDown(): void
    {
        Edition::fake(null);
        parent::tearDown();
    }

    /**
     * Test a Cloud Team tenant is blocked from adding a 16th worker (admin, coordinator, worker),
     * while adding client users remains unlimited.
     */
    public function test_cloud_team_tenant_is_blocked_from_adding_16th_worker_while_clients_are_unlimited(): void
    {
        Edition::fake(true);

        $tenant = Tenant::factory()->create([
            'plan' => 'team',
            'subscription_status' => 'active',
        ]);

        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        // Add 14 workers (1 admin + 14 workers = 15 active workers, reaching Cloud Team quota)
        User::factory()->count(14)->worker()->create(['tenant_id' => $tenant->id]);

        $this->assertEquals(15, $tenant->activeWorkersCount());
        $this->assertEquals(15, $tenant->maxWorkersLimit());
        $this->assertFalse($tenant->canAddWorker());

        $this->actingAs($admin);

        // Attempt to create a 16th worker -> should be blocked with error
        Livewire::test(TeamManagement::class)
            ->call('openCreateUserModal')
            ->set('userName', 'Worker Sixteen')
            ->set('userEmail', 'worker16@example.com')
            ->set('userPassword', 'Password123!')
            ->set('userRole', 'worker')
            ->call('saveUser')
            ->assertHasErrors(['userRole']);

        $this->assertDatabaseMissing('users', [
            'email' => 'worker16@example.com',
        ]);

        // Attempt to create a client user -> should succeed as clients are unlimited
        Livewire::test(TeamManagement::class)
            ->call('openCreateUserModal')
            ->set('userName', 'Client Customer')
            ->set('userEmail', 'client@example.com')
            ->set('userPassword', 'Password123!')
            ->set('userRole', 'client')
            ->call('saveUser')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'client@example.com',
            'role' => 'client',
        ]);
    }

    /**
     * Test upgrading a tenant to Cloud Business unlocks unlimited workers and advanced features.
     */
    public function test_upgrading_tenant_to_cloud_business_unlocks_unlimited_workers_and_features(): void
    {
        Edition::fake(true);

        $tenant = Tenant::factory()->create([
            'plan' => 'team',
            'subscription_status' => 'active',
        ]);

        $this->assertEquals('team', $tenant->effectivePlan());
        $this->assertEquals(15, $tenant->maxWorkersLimit());
        $this->assertFalse($tenant->canUseFeature('strict_b2b_isolation'));
        $this->assertFalse($tenant->canUseFeature('gdpr_privacy_mode'));
        $this->assertFalse($tenant->canUseFeature('custom_telegram_bot'));
        $this->assertFalse($tenant->canUseFeature('custom_domain'));

        // Dev mode simulation when Stripe secret is not configured
        Config::set('tantou.stripe.secret', null);
        $service = app(StripeBillingService::class);
        $redirectUrl = $service->createCheckoutSession($tenant, 'business', 'yearly');

        $tenant->refresh();

        $this->assertStringContainsString('/board', $redirectUrl);
        $this->assertEquals('business', $tenant->plan);
        $this->assertEquals('business', $tenant->effectivePlan());
        $this->assertEquals('yearly', $tenant->billing_interval);
        $this->assertNull($tenant->maxWorkersLimit());
        $this->assertTrue($tenant->canAddWorker());
        $this->assertTrue($tenant->canUseFeature('strict_b2b_isolation'));
        $this->assertTrue($tenant->canUseFeature('gdpr_privacy_mode'));
        $this->assertTrue($tenant->canUseFeature('custom_telegram_bot'));
        $this->assertTrue($tenant->canUseFeature('custom_domain'));
    }

    /**
     * Test Stripe webhook syncs subscription checkout, updates, and cancellations.
     */
    public function test_stripe_webhook_syncs_subscription_events(): void
    {
        $tenant = Tenant::factory()->create([
            'plan' => 'team',
            'subscription_status' => 'trialing',
        ]);

        // 1. Webhook: checkout.session.completed
        $payloadCompleted = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'metadata' => [
                        'tenant_id' => $tenant->id,
                        'plan' => 'business',
                        'interval' => 'monthly',
                    ],
                    'customer' => 'cus_test_999',
                    'subscription' => 'sub_test_888',
                ],
            ],
        ];

        $response = $this->postJson('/api/stripe/webhook', $payloadCompleted);
        $response->assertOk()->assertJson(['received' => true]);

        $tenant->refresh();
        $this->assertEquals('business', $tenant->plan);
        $this->assertEquals('active', $tenant->subscription_status);
        $this->assertEquals('cus_test_999', $tenant->stripe_customer_id);
        $this->assertEquals('sub_test_888', $tenant->stripe_subscription_id);

        // 2. Webhook: customer.subscription.deleted
        $payloadDeleted = [
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'id' => 'sub_test_888',
                    'status' => 'canceled',
                ],
            ],
        ];

        $response = $this->postJson('/api/stripe/webhook', $payloadDeleted);
        $response->assertOk()->assertJson(['received' => true]);

        $tenant->refresh();
        $this->assertEquals('canceled', $tenant->subscription_status);
    }

    /**
     * Test Stripe webhook HMAC signature verification rejects invalid signatures.
     */
    public function test_stripe_webhook_rejects_invalid_signature_when_secret_set(): void
    {
        $webhookSecret = 'whsec_test_secret_key_123';
        Config::set('tantou.stripe.webhook_secret', $webhookSecret);

        $payload = ['type' => 'ping'];
        $rawContent = json_encode($payload);

        // Missing signature header -> 400
        $responseNoHeader = $this->call(
            'POST',
            '/api/stripe/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $rawContent
        );
        $responseNoHeader->assertStatus(400);

        // Invalid signature -> 400
        $responseBadSig = $this->call(
            'POST',
            '/api/stripe/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=123456,v1=invalid_signature',
            ],
            $rawContent
        );
        $responseBadSig->assertStatus(400);

        // Valid signature -> 200
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$rawContent}";
        $validSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);

        $responseValid = $this->call(
            'POST',
            '/api/stripe/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$validSignature}",
            ],
            $rawContent
        );
        $responseValid->assertOk();
    }

    /**
     * Test Single-Tenant Self-Hosted Community mode bypasses all quotas and unlocks all features.
     */
    public function test_community_mode_bypasses_all_quotas_and_feature_gates(): void
    {
        Edition::fake(false); // Community / Self-Hosted

        $tenant = Tenant::factory()->create([
            'plan' => 'team',
            'subscription_status' => 'unpaid',
        ]);

        $this->assertEquals('business', $tenant->effectivePlan());
        $this->assertTrue($tenant->hasActiveSubscription());
        $this->assertNull($tenant->maxWorkersLimit());
        $this->assertNull($tenant->maxDepartmentsLimit());
        $this->assertTrue($tenant->canAddWorker());
        $this->assertTrue($tenant->canUseFeature('strict_b2b_isolation'));
        $this->assertTrue($tenant->canUseFeature('gdpr_privacy_mode'));
        $this->assertTrue($tenant->canUseFeature('custom_telegram_bot'));
        $this->assertTrue($tenant->canUseFeature('custom_domain'));
    }

    /**
     * Test Hellenic Demo organization always has Business plan features unlocked.
     */
    public function test_demo_tenant_has_all_business_features_unlocked(): void
    {
        Edition::fake(true);

        $demoTenant = Tenant::factory()->create([
            'slug' => 'hellenic-demo-ops',
            'plan' => 'business',
            'subscription_status' => 'active',
        ]);

        $this->assertTrue($demoTenant->isDemo());
        $this->assertEquals('business', $demoTenant->effectivePlan());
        $this->assertTrue($demoTenant->hasActiveSubscription());
        $this->assertNull($demoTenant->maxWorkersLimit());
        $this->assertTrue($demoTenant->canAddWorker());
        $this->assertTrue($demoTenant->canUseFeature('strict_b2b_isolation'));
        $this->assertTrue($demoTenant->canUseFeature('gdpr_privacy_mode'));
        $this->assertTrue($demoTenant->canUseFeature('custom_telegram_bot'));
        $this->assertTrue($demoTenant->canUseFeature('custom_domain'));
    }

    /**
     * Test workspace settings modal enforces feature gates for Cloud Team tenants.
     */
    public function test_workspace_settings_blocks_business_features_on_team_plan(): void
    {
        Edition::fake(true);

        $tenant = Tenant::factory()->create([
            'plan' => 'team',
            'subscription_status' => 'active',
        ]);
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($admin);

        Livewire::test(CapacityBoard::class)
            ->call('openWorkspaceSettingsModal')
            ->set('strictClientIsolation', true)
            ->call('saveWorkspaceSettings')
            ->assertHasErrors(['strictClientIsolation']);

        Livewire::test(CapacityBoard::class)
            ->call('openWorkspaceSettingsModal')
            ->set('telegramEnabled', true)
            ->set('telegramPrivacyMode', true)
            ->call('saveWorkspaceSettings')
            ->assertHasErrors(['telegramPrivacyMode']);

        Livewire::test(CapacityBoard::class)
            ->call('openWorkspaceSettingsModal')
            ->set('telegramEnabled', true)
            ->set('telegramBotToken', '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11')
            ->call('saveWorkspaceSettings')
            ->assertHasErrors(['telegramBotToken']);

        Livewire::test(CapacityBoard::class)
            ->call('openWorkspaceSettingsModal')
            ->set('tenantCustomDomain', 'ops.acme-corp.com')
            ->call('saveWorkspaceSettings')
            ->assertHasErrors(['tenantCustomDomain']);
    }

    /**
     * Test a Cloud Business tenant can configure a custom domain and access the workspace through it.
     */
    public function test_business_tenant_can_configure_and_access_custom_domain(): void
    {
        Edition::fake(true);

        $tenant = Tenant::factory()->create([
            'plan' => 'business',
            'subscription_status' => 'active',
        ]);
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($admin);

        Livewire::test(CapacityBoard::class)
            ->call('openWorkspaceSettingsModal')
            ->set('tenantCustomDomain', 'ops.acme-corp.com')
            ->call('saveWorkspaceSettings')
            ->assertHasNoErrors();

        $tenant->refresh();
        $this->assertEquals('ops.acme-corp.com', $tenant->custom_domain);

        // Verify request with Host: ops.acme-corp.com resolves currentTenant
        $response = $this->get('http://ops.acme-corp.com/board');
        $response->assertOk();
        $this->assertEquals($tenant->id, app('currentTenant')->id);
    }

    /**
     * Test Stripe webhook returns 404 in Community Edition.
     */
    public function test_stripe_webhook_returns_404_in_community_edition(): void
    {
        Edition::fake(false);

        $response = $this->postJson('/api/stripe/webhook', [
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => []],
        ]);

        $response->assertStatus(404);
    }
}
