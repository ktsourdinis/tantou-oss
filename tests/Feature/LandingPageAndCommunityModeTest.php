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
        $response->assertSee('Dedicated Managed Workspace with Zero-Config Auto-SSL &amp; Instant Onboarding', false);
        $response->assertDontSee('Dedicated {slug}.tantou.app workspace', false);
        $response->assertSee('mailto:tantou@tsourdinis.info?subject=Tantou%20Enterprise%20%26%20Public%20Sector%20White-Label%20Inquiry', false);

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
            $this->assertStringContainsString('git clone https://github.com/ktsourdinis/tantou-oss.git tantou && cd tantou', $content, "Missing clone command in {$relPath}");
            $this->assertStringContainsString('cp .env.example .env && cp docker-compose.example.yml docker-compose.yml && cp Caddyfile.example Caddyfile', $content, "Missing config copy in {$relPath}");
            $this->assertStringContainsString('docker compose up -d --build', $content, "Missing compose build in {$relPath}");
            $this->assertStringContainsString('docker exec -u 1000:1000 tantou_app composer install --no-dev --optimize-autoloader', $content, "Missing manual composer fallback in {$relPath}");
            $this->assertStringContainsString('docker exec -u 1000:1000 tantou_app php artisan key:generate', $content, "Missing key:generate in {$relPath}");
            $this->assertStringContainsString('docker exec -u 1000:1000 tantou_app php artisan migrate --force && docker exec -u 1000:1000 tantou_app php artisan storage:link', $content, "Missing migrate & storage:link in {$relPath}");
        }

        $bootstrapApp = file_get_contents(base_path('bootstrap/app.php'));
        $this->assertStringContainsString("\$middleware->trustProxies(at: '*');", $bootstrapApp);

        $appServiceProvider = file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $this->assertStringContainsString("str_starts_with((string) config('app.url'), 'https://')", $appServiceProvider);
        $this->assertStringContainsString("URL::forceScheme('https');", $appServiceProvider);

        $caddyfile = file_get_contents(base_path('Caddyfile.example'));
        $this->assertStringContainsString('root * /var/www/html/public', $caddyfile);
        $this->assertStringContainsString('reverse_proxy tantou_reverb:8080', $caddyfile);
        $this->assertStringContainsString('php_fastcgi tantou_app:9000', $caddyfile);

        $compose = file_get_contents(base_path('docker-compose.example.yml'));
        $this->assertStringContainsString('proxy_network', $compose);
        $this->assertStringContainsString('external: true', $compose);

        foreach (['README.md', 'docs/USER_AND_ADMIN_GUIDE.md'] as $docPath) {
            $docContent = file_get_contents(base_path($docPath));
            $this->assertStringContainsString('Running Behind an External Reverse Proxy (Caddy / Nginx / Traefik)', $docContent);
            $this->assertStringContainsString('reverse_proxy tantou_caddy:80', $docContent);
            $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $docContent);
            $this->assertStringContainsString('Upgrading a Self-Hosted Instance', $docContent);
            $this->assertStringContainsString('./update.sh', $docContent);
        }

        $updateScriptPath = base_path('update.sh');
        $this->assertFileExists($updateScriptPath);
        $updateScript = file_get_contents($updateScriptPath);
        $this->assertStringContainsString('git checkout -- public/build/ 2>/dev/null || true', $updateScript);
        $this->assertStringContainsString('git pull --ff-only', $updateScript);
        $this->assertStringContainsString('php artisan migrate --force', $updateScript);
        $this->assertStringContainsString('php artisan queue:restart', $updateScript);
        $this->assertStringNotContainsString('npm ci', $updateScript);
        $this->assertStringNotContainsString('npm run build', $updateScript);
    }

    /**
     * Test landing page renders Open Graph and Twitter Card meta tags and og-cover.png exists at 1200x627.
     */
    public function test_landing_page_renders_open_graph_and_twitter_card_meta_tags(): void
    {
        $ogPath = public_path('images/og-cover.png');
        $this->assertFileExists($ogPath);

        $imageSize = getimagesize($ogPath);
        $this->assertIsArray($imageSize);
        $this->assertSame(1200, $imageSize[0]);
        $this->assertSame(627, $imageSize[1]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('property="og:title" content="Tantou (担当) — Human Capacity OS & Work Coordination"', false);
        $response->assertSee('property="og:description"', false);
        $response->assertSee('property="og:image"', false);
        $response->assertSee('images/og-cover.png', false);
        $response->assertSee('property="og:image:width" content="1200"', false);
        $response->assertSee('property="og:image:height" content="627"', false);
        $response->assertSee('name="twitter:card" content="summary_large_image"', false);
        $response->assertSee('name="twitter:image"', false);
    }
}
