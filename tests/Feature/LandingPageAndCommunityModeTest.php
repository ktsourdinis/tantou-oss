<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LandingPageAndCommunityModeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test landing page renders all 5 sections: Hero, 6 Features, 4 Architectures, 3 Pricing Plans, and Self-Host Guide.
     */
    public function test_landing_page_renders_comprehensive_sections_and_ctas(): void
    {
        Edition::fake(true); // Cloud edition enabled for pricing display
        Config::set('tantou.hide_marketing', false);

        $response = $this->get('/');

        $response->assertStatus(200);

        // Section 1: Hero & Actions
        $response->assertSee("Know who's available.");
        $response->assertSee("Know what's getting done.");
        $response->assertSee("See your team's actual workload");
        $response->assertSee('Route every request to the right person');
        $response->assertSee('Keep every task visible until completion');
        $response->assertSee('Try Live Interactive Demo');
        $response->assertSee('No signup required • Resets every 2 hours');
        $response->assertSee('Launch Cloud Workspace');
        $response->assertSee('Self-Host (Single-Tenant)');

        // Section 2: 6 Features
        $response->assertSee('Capacity Radar &amp; Smart Queue (W1–W5)', false);
        $response->assertSee('3-Tier Visibility Firewalls &amp; B2B Isolation', false);
        $response->assertSee('Public QR Drop-Box &amp; Live Tracking (/submit &amp; /track)', false);
        $response->assertSee('Co-op Bounties &amp; Workflow Chains', false);
        $response->assertSee('2-Way Telegram Bot (GDPR Privacy Mode)');
        $response->assertSee('SLA Analytics &amp; Multi-Timezone Aware', false);

        // Section 3: 4 Architectures
        $response->assertSee('Internal Enterprise &amp; Hospital IT', false);
        $response->assertSee('B2B Managed Service Providers (MSPs)');
        $response->assertSee('Facility QR Drop-Box');
        $response->assertSee('Multi-Site &amp; Shared Services', false);

        // Section 4: 3 Pricing Tiers
        $response->assertSee('Self-Hosted Community');
        $response->assertSee('Tantou Cloud Managed');
        $response->assertSee('Public Sector &amp; Enterprise', false);
        $response->assertSee('€0');
        $response->assertSee('€8');
        $response->assertSee('€3,600');

        // Section 5: Self-Host Quickstart
        $response->assertSee('Self-Host Tantou on Your Own Server in 60 Seconds');
        $response->assertSee('git clone https://github.com/ktsourdinis/tantou-oss.git');
        $response->assertSee('docker compose up -d');
        $response->assertSee('docker exec -it tantou_app php artisan migrate --seed');
        $response->assertSee('Intranet IP Mode vs Domain Mode:');
    }

    /**
     * Test marketing and pricing sections are hidden when TANTOU_HIDE_MARKETING is true.
     */
    public function test_pricing_and_marketing_hidden_when_configured(): void
    {
        Edition::fake(false);
        Config::set('tantou.hide_marketing', true);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertDontSee('No Crippled Free Cloud Tiers. Honest Pricing.');
        $response->assertDontSee('Start Cloud Team →');
        $response->assertDontSee('Start Cloud Business →');

        // Self-host guide remains accessible
        $response->assertSee('Self-Host Tantou on Your Own Server in 60 Seconds');
    }

    /**
     * Test Community edition behavior forces single routing mode and blocks /register when tenant exists.
     */
    public function test_community_edition_forces_single_tenant_and_blocks_register(): void
    {
        Edition::fake(null);
        Config::set('tantou.edition', 'community');
        Config::set('tantou.routing_mode', 'auto');

        $this->assertTrue(Edition::isCommunity());
        $this->assertFalse(Edition::isCloud());
        $this->assertEquals('single', Edition::routingMode());

        // Create an existing tenant
        Tenant::create([
            'name' => 'Self Hosted Corp',
            'slug' => 'self-hosted-corp',
        ]);

        $this->assertFalse(Edition::canRegisterNewTenant());

        // Visiting /register redirects to /login in single-tenant community mode
        $response = $this->get('/register');
        $response->assertRedirect(route('login'));
    }

    /**
     * Test Greek localization on Landing Page.
     */
    public function test_landing_page_renders_greek_translations(): void
    {
        Edition::fake(true);
        Config::set('tantou.hide_marketing', false);

        $response = $this->withSession(['locale' => 'el'])->get('/');

        $response->assertStatus(200);
        $response->assertSee('Το Open-Core Λειτουργικό Σύστημα Ανθρώπινης Χωρητικότητας');
        $response->assertSee('για Επιχειρήσεις Πραγματικού Χρόνου');
        $response->assertSee('Δοκιμάστε το Διαδραστικό Demo');
        $response->assertSee('Χωρίς εγγραφή • Επαναφορά κάθε 2 ώρες');
        $response->assertSee('Αυτοφιλοξενία (Single-Tenant)');
    }

    /**
     * Test Docker auto-bootstrap entrypoint and quickstart guides are complete.
     */
    public function test_docker_auto_bootstrap_entrypoint_and_quickstart_guides_are_complete(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));
        $this->assertStringContainsString('nodejs', $dockerfile);
        $this->assertStringContainsString('npm', $dockerfile);
        $this->assertStringContainsString('ENTRYPOINT ["docker-entrypoint.sh"]', $dockerfile);
        $this->assertStringContainsString('CMD ["php-fpm"]', $dockerfile);

        $entrypointPath = base_path('docker-entrypoint.sh');
        $this->assertFileExists($entrypointPath);
        $entrypoint = file_get_contents($entrypointPath);
        $this->assertStringContainsString('/var/www/html/vendor/autoload.php', $entrypoint);
        $this->assertStringContainsString('composer install --no-interaction --prefer-dist --optimize-autoloader', $entrypoint);
        $this->assertStringContainsString('/var/www/html/public/build/manifest.json', $entrypoint);
        $this->assertStringContainsString('npm ci --no-audit --no-fund && npm run build', $entrypoint);
        $this->assertStringContainsString('exec "$@"', $entrypoint);

        foreach (['README.md', 'resources/views/welcome.blade.php', 'docs/USER_AND_ADMIN_GUIDE.md'] as $relPath) {
            $content = file_get_contents(base_path($relPath));
            $this->assertStringContainsString('cp docker-compose.example.yml docker-compose.yml', $content, "Missing compose copy in {$relPath}");
            $this->assertStringContainsString('cp Caddyfile.example Caddyfile', $content, "Missing Caddyfile copy in {$relPath}");
            $this->assertStringContainsString('docker exec -u 1000:1000 tantou_app composer install --no-dev --optimize-autoloader', $content, "Missing manual composer fallback in {$relPath}");
        }
    }
}
