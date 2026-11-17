<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Step46SustainablePricingAndLicenseContradictionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test composer.json metadata, authors, support, and AGPL-3.0-or-later license.
     */
    public function test_composer_json_is_completely_branded_and_licensed(): void
    {
        $composerPath = base_path('composer.json');
        $this->assertFileExists($composerPath);

        $json = json_decode(file_get_contents($composerPath), true);

        $this->assertSame('tantou/tantou', $json['name']);
        $this->assertSame('AGPL-3.0-or-later', $json['license']);
        $this->assertStringNotContainsString('laravel/laravel', file_get_contents($composerPath));

        $authors = collect($json['authors'] ?? [])->pluck('name')->all();
        $this->assertContains('Konstantinos Tsourdinis', $authors);
        $this->assertContains('HELLENIC WEB3 STUDIO', $authors);

        $this->assertSame('https://github.com/ktsourdinis/tantou-oss/issues', $json['support']['issues'] ?? null);
        $this->assertSame('https://github.com/ktsourdinis/tantou-oss', $json['support']['source'] ?? null);

        $keywords = $json['keywords'] ?? [];
        $this->assertContains('tantou', $keywords);
        $this->assertContains('capacity-os', $keywords);
    }

    /**
     * Test README.md and USER_AND_ADMIN_GUIDE.md contain no paywall routing leaks or MIT license contradictions.
     */
    public function test_public_docs_contain_no_multi_tenant_paywall_leaks(): void
    {
        $readme = file_get_contents(base_path('README.md'));
        $docs = file_get_contents(base_path('docs/USER_AND_ADMIN_GUIDE.md'));

        // Paywall leak checks
        $this->assertStringNotContainsString('TANTOU_ROUTING_MODE=subdomain', $readme);
        $this->assertStringNotContainsString('TANTOU_ROUTING_MODE=path', $readme);
        $this->assertStringNotContainsString('TANTOU_ROUTING_MODE=subdomain', $docs);
        $this->assertStringNotContainsString('TANTOU_ROUTING_MODE=path', $docs);

        // License checks
        $this->assertStringContainsString('GNU AGPL v3.0 (AGPL-3.0-or-later)', $readme);
        $this->assertStringContainsString('GNU AGPL v3.0 (AGPL-3.0-or-later)', $docs);
    }

    /**
     * Test sustainable pricing tiers are present on the landing page and README.md.
     */
    public function test_sustainable_pricing_tiers_rendered(): void
    {
        $readme = file_get_contents(base_path('README.md'));

        $this->assertStringContainsString('€8 / active worker / mo', $readme);
        $this->assertStringContainsString('€3,600 / year', $readme);
        $this->assertStringContainsString('Community Self-Hosted', $readme);
        $this->assertStringContainsString('Tantou Cloud Managed', $readme);
        $this->assertStringContainsString('Public Sector & Enterprise', $readme);

        // Landing page checks
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Tantou Cloud Managed');
        $response->assertSee('€8');
        $response->assertSee('€3,600');
        $response->assertSee('Public Sector &amp; Enterprise', false);
    }

    /**
     * Test zero forbidden internal identifiers in git-tracked files.
     */
    public function test_zero_forbidden_internal_identifiers_across_repository(): void
    {
        $ip = implode('.', ['10', '1', '7', '74']);
        $email = 'k'.'.'.'tsourdinis';
        $path = '/'.'home'.'/'.'dockerdata';

        $escaped = sprintf('(%s|%s|%s)', preg_quote($ip, '/'), preg_quote($email, '/'), preg_quote($path, '/'));

        $output = shell_exec(sprintf('git grep -nE "%s" 2>/dev/null', $escaped));
        $this->assertEmpty(trim((string) $output), 'Found internal identifiers: '.$output);
    }
}
