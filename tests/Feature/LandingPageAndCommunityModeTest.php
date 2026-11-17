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
        $response->assertSee('The Open-Core Human Capacity OS');
        $response->assertSee('for Real-Time Operations');
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
}
