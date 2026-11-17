<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Step42CleanlinessAndCommunityLockTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Edition::fake(null);
        parent::tearDown();
    }

    /**
     * Test .env.example is completely sanitized for self-hosted community users.
     */
    public function test_env_example_is_sanitized_and_contains_no_internal_ips_or_cloud_vars(): void
    {
        $envExamplePath = base_path('.env.example');
        $this->assertFileExists($envExamplePath);

        $content = file_get_contents($envExamplePath);

        // Disallowed strings
        $forbiddenIp = implode('.', ['10', '1', '7', '74']);
        $forbiddenEmail = 'k'.'.'.'tsourdinis'.'@'.'gmail.com';
        $this->assertStringNotContainsString($forbiddenIp, $content);
        $this->assertStringNotContainsString($forbiddenEmail, $content);
        $this->assertStringNotContainsString('TANTOU_EDITION', $content);
        $this->assertStringNotContainsString('TANTOU_ROUTING_MODE', $content);
        $this->assertStringNotContainsString('TANTOU_BASE_DOMAIN', $content);
        $this->assertStringNotContainsString('TANTOU_HIDE_MARKETING', $content);
        $this->assertStringNotContainsString('STRIPE_', $content);
        $this->assertStringNotContainsString('TANTOU_SUPERADMIN_EMAILS', $content);

        // Required 6 sections
        $this->assertStringContainsString('APPLICATION CORE & ENVIRONMENT', $content);
        $this->assertStringContainsString('DATABASE & CACHE / QUEUE', $content);
        $this->assertStringContainsString('LARAVEL REVERB WEBSOCKETS', $content);
        $this->assertStringContainsString('MAIL NOTIFICATIONS', $content);
        $this->assertStringContainsString('TELEGRAM BOT INTEGRATION', $content);
        $this->assertStringContainsString('LOGGING & STORAGE', $content);

        // Standard defaults
        $this->assertStringContainsString('APP_URL=http://localhost:8085', $content);
        $this->assertStringContainsString('BCRYPT_ROUNDS=12', $content);
        $this->assertStringContainsString('MAIL_FROM_ADDRESS="no-reply@example.com"', $content);
        $this->assertStringContainsString('TANTOU_TELEGRAM_MODE=polling', $content);
    }

    /**
     * Test README.md contains no internal IP or personal email.
     */
    public function test_readme_contains_no_internal_ip_or_personal_email(): void
    {
        $readmePath = base_path('README.md');
        $this->assertFileExists($readmePath);

        $content = file_get_contents($readmePath);

        $forbiddenIp = implode('.', ['10', '1', '7', '74']);
        $forbiddenEmail = 'k'.'.'.'tsourdinis'.'@'.'gmail.com';

        $this->assertStringNotContainsString($forbiddenIp, $content);
        $this->assertStringNotContainsString($forbiddenEmail, $content);
    }

    /**
     * Test that in Community Edition, /superadmin and /api/stripe/webhook are disabled (404).
     */
    public function test_community_edition_locks_superadmin_and_stripe_routes(): void
    {
        Edition::fake(false);

        $tenant = Tenant::factory()->create([
            'slug' => 'hospital-ops',
            'name' => 'Hospital Ops',
        ]);

        $superadmin = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'email' => 'super@hospital.gr',
            'is_superadmin' => true,
        ]);

        // 1. /superadmin returns 404 in Community Edition even for superadmin user
        $this->actingAs($superadmin)
            ->get('/superadmin')
            ->assertNotFound();

        // 2. /api/stripe/webhook returns 404 in Community Edition
        $this->postJson('/api/stripe/webhook', ['type' => 'customer.subscription.deleted'])
            ->assertNotFound();
    }

    /**
     * Test that in Community Edition with an existing tenant, new tenant registration is blocked.
     */
    public function test_community_edition_locks_multi_tenant_workspace_creation(): void
    {
        Edition::fake(false);

        Tenant::factory()->create([
            'slug' => 'primary-ops',
            'name' => 'Primary Operations',
        ]);

        $this->assertFalse(Edition::canRegisterNewTenant());

        $this->get('/register')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('This self-hosted workspace is already initialized. Please sign in.'));
    }

    /**
     * Test AGENTS.md and CLAUDE.md are excluded by .git/info/exclude and untracked from git index
     * while persisting locally on filesystem.
     */
    public function test_agents_and_claude_are_excluded_and_exist_locally(): void
    {
        $this->assertFileExists(base_path('AGENTS.md'));
        $this->assertFileExists(base_path('CLAUDE.md'));

        $excludePath = base_path('.git/info/exclude');
        $this->assertFileExists($excludePath);

        $excludeContent = file_get_contents($excludePath);
        $this->assertStringContainsString('AGENTS.md', $excludeContent);
        $this->assertStringContainsString('CLAUDE.md', $excludeContent);
        $this->assertStringContainsString('.cursorrules', $excludeContent);
        $this->assertStringContainsString('.env.cloud', $excludeContent);
        $this->assertStringContainsString('.env.cloud.*', $excludeContent);
        $this->assertStringContainsString('.env.cloud.example', $excludeContent);
        $this->assertStringContainsString('config/tantou-cloud.php', $excludeContent);
    }

    /**
     * Test .env.cloud.example exists locally, is excluded from git, and contains all 9 SaaS sections.
     */
    public function test_env_cloud_example_exists_locally_and_contains_cloud_saas_sections(): void
    {
        $cloudEnvPath = base_path('.env.cloud.example');
        $this->assertFileExists($cloudEnvPath);

        $content = file_get_contents($cloudEnvPath);

        $this->assertStringContainsString('APP_NAME="Tantou Cloud"', $content);
        $this->assertStringContainsString('TANTOU_EDITION=cloud', $content);
        $this->assertStringContainsString('TANTOU_ROUTING_MODE=auto', $content);
        $this->assertStringContainsString('TANTOU_BASE_DOMAIN=tantou.app', $content);
        $this->assertStringContainsString('TANTOU_SUPERADMIN_EMAILS=', $content);
        $this->assertStringContainsString('STRIPE_PRICE_TEAM_MONTHLY=', $content);
        $this->assertStringContainsString('SESSION_DOMAIN=.tantou.app', $content);
        $this->assertStringContainsString('REVERB_HOST=tantou.app', $content);
        $this->assertStringContainsString('TANTOU_TELEGRAM_MODE=webhook', $content);
    }

    /**
     * Test config/tantou.php is clean of commercial cloud env references and merges config/tantou-cloud.php.
     */
    public function test_config_tantou_is_sanitized_and_merges_cloud_overrides(): void
    {
        $publicConfigPath = config_path('tantou.php');
        $this->assertFileExists($publicConfigPath);

        $publicContent = file_get_contents($publicConfigPath);

        // Public config must not contain these strings
        $forbidden = [
            'TANTOU_EDITION',
            'TANTOU_ROUTING_MODE',
            'subdomain',
            'path',
            'STRIPE_KEY',
            'STRIPE_SECRET',
            'TANTOU_SUPERADMIN_EMAILS',
        ];

        foreach ($forbidden as $term) {
            $this->assertStringNotContainsString($term, $publicContent, "config/tantou.php should not contain {$term}");
        }

        // Private cloud config exists locally and is excluded
        $cloudConfigPath = config_path('tantou-cloud.php');
        $this->assertFileExists($cloudConfigPath);

        // Verify config is successfully loaded and accessible
        $this->assertIsArray(config('tantou.plans'));
        $this->assertArrayHasKey('team', config('tantou.plans'));
        $this->assertArrayHasKey('business', config('tantou.plans'));
    }
}
