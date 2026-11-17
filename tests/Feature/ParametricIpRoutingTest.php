<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\CapacityBoard;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class ParametricIpRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('tantou.edition', 'cloud');
        Config::set('tantou.routing_mode', 'auto');
        Config::set('tantou.base_domain', 'tantou.test');
        Config::set('app.base_domain', 'tantou.test');
        Config::set('session.domain', '.tantou.test');
    }

    protected function tearDown(): void
    {
        Edition::fake(null);
        parent::tearDown();
    }

    /**
     * Test Edition::isIpAddress and Edition::usesSubdomains helpers accurately detect
     * raw IP intranet addresses vs domain names.
     */
    public function test_edition_helpers_detect_ips_and_subdomain_support(): void
    {
        Edition::fake(true);

        // IP hostnames
        $this->assertTrue(Edition::isIpAddress('192.168.1.100'));
        $this->assertTrue(Edition::isIpAddress('192.168.1.100:8085'));
        $this->assertTrue(Edition::isIpAddress('192.168.1.100'));
        $this->assertTrue(Edition::isIpAddress('127.0.0.1'));
        $this->assertTrue(Edition::isIpAddress('127.0.0.1:8000'));

        // Domain hostnames
        $this->assertFalse(Edition::isIpAddress('tantou.test'));
        $this->assertFalse(Edition::isIpAddress('tantou.test:8085'));
        $this->assertFalse(Edition::isIpAddress('acme.tantou.test'));

        // usesSubdomains with routing_mode = auto
        $ipRequest = Request::create('http://192.168.1.100:8085/board');
        $domainRequest = Request::create('http://tantou.test:8085/board');

        $this->assertFalse(Edition::usesSubdomains($ipRequest));
        $this->assertTrue(Edition::usesSubdomains($domainRequest));

        // Explicit routing_mode overrides
        Config::set('tantou.routing_mode', 'subdomain');
        $this->assertTrue(Edition::usesSubdomains($ipRequest));

        Config::set('tantou.routing_mode', 'path');
        $this->assertFalse(Edition::usesSubdomains($domainRequest));

        Config::set('tantou.routing_mode', 'single');
        $this->assertFalse(Edition::usesSubdomains($domainRequest));
    }

    /**
     * Test Edition::workspaceUrl produces accurate URLs for both subdomain and IP modes.
     */
    public function test_edition_workspace_url_generates_correct_urls_for_ip_and_domain(): void
    {
        Edition::fake(true);
        $tenant = Tenant::factory()->create(['slug' => 'hospital-ops', 'name' => 'Hospital Ops']);

        // 1. Accessed via raw IP address (192.168.1.100:8085)
        $ipRequest = Request::create('http://192.168.1.100:8085/login');
        $ipBoardUrl = Edition::workspaceUrl($tenant, '/board', $ipRequest);
        $this->assertEquals('http://192.168.1.100:8085/board?workspace=hospital-ops', $ipBoardUrl);

        $ipRootUrl = Edition::workspaceUrl($tenant, '', $ipRequest);
        $this->assertEquals('http://192.168.1.100:8085?workspace=hospital-ops', $ipRootUrl);

        // 2. Accessed via domain (tantou.test:8085)
        $domainRequest = Request::create('http://tantou.test:8085/login');
        $domainBoardUrl = Edition::workspaceUrl($tenant, '/board', $domainRequest);
        $this->assertEquals('http://hospital-ops.tantou.test:8085/board', $domainBoardUrl);
    }

    /**
     * Test when accessed via an IP host (http://192.168.1.100:8085/login), session domain is set to null,
     * and logging in redirects cleanly to 192.168.1.100:8085/board?workspace={slug} without invalid subdomains.
     */
    public function test_ip_access_sets_session_domain_null_and_redirects_with_workspace_param(): void
    {
        Edition::fake(true);
        $tenant = Tenant::factory()->create(['slug' => 'clinic-branch-a', 'name' => 'Clinic Branch A']);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'email' => 'admin@clinic-a.local',
            'password' => 'Tantou#2026!Secure',
        ]);

        // 1. Initial GET on IP sets session.domain to null dynamically
        $response = $this->withServerVariables(['HTTP_HOST' => '192.168.1.100:8085', 'SERVER_PORT' => 8085])
            ->get('http://192.168.1.100:8085/login');

        $response->assertStatus(200);
        $this->assertNull(config('session.domain'));

        // 2. Logging in on IP redirects to IP with query parameter ?workspace=clinic-branch-a
        $this->withServerVariables(['HTTP_HOST' => '192.168.1.100:8085', 'SERVER_PORT' => 8085]);

        Livewire::test(Login::class)
            ->set('email', 'admin@clinic-a.local')
            ->set('password', 'Tantou#2026!Secure')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect('http://192.168.1.100:8085/board?workspace=clinic-branch-a');

        $this->assertAuthenticatedAs($user);
        $this->assertEquals($tenant->id, session('active_tenant_id'));
    }

    /**
     * Test changing workspace slug in Admin Workspace Settings when on an IP host
     * redirects cleanly to the same IP host with the new workspace slug query parameter.
     */
    public function test_workspace_settings_slug_change_on_ip_redirects_safely(): void
    {
        Edition::fake(true);
        $tenant = Tenant::factory()->create(['slug' => 'old-slug', 'name' => 'Old Workspace']);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $admin = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
        ]);

        $this->actingAs($admin);
        $this->withServerVariables(['HTTP_HOST' => '192.168.1.100:8085', 'SERVER_PORT' => 8085])
            ->get('http://192.168.1.100:8085/board');

        Livewire::test(CapacityBoard::class)
            ->call('openWorkspaceSettingsModal')
            ->set('tenantSlug', 'new-intranet-slug')
            ->call('saveWorkspaceSettings')
            ->assertHasNoErrors()
            ->assertRedirect('http://192.168.1.100:8085/board?workspace=new-intranet-slug');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'slug' => 'new-intranet-slug',
        ]);
    }

    /**
     * Test tenant resolution via query parameter ?workspace={slug} when accessed via IP.
     */
    public function test_tenant_resolution_via_workspace_query_parameter_on_ip(): void
    {
        Edition::fake(true);
        $tenantAlpha = Tenant::factory()->create(['slug' => 'tenant-alpha', 'name' => 'Tenant Alpha']);
        $tenantBeta = Tenant::factory()->create(['slug' => 'tenant-beta', 'name' => 'Tenant Beta']);

        $departmentAlpha = Department::factory()->create(['tenant_id' => $tenantAlpha->id]);
        $departmentBeta = Department::factory()->create(['tenant_id' => $tenantBeta->id]);

        $user = User::factory()->worker()->create([
            'tenant_id' => $tenantAlpha->id,
            'department_id' => $departmentAlpha->id,
        ]);

        $this->actingAs($user);

        // Access with ?workspace=tenant-beta resolves tenant-beta
        $response = $this->withServerVariables(['HTTP_HOST' => '192.168.1.100:8085', 'SERVER_PORT' => 8085])
            ->get('http://192.168.1.100:8085/board?workspace=tenant-beta');

        $response->assertStatus(200);
        $this->assertTrue(app()->bound('currentTenant'));
        $this->assertEquals($tenantBeta->id, app('currentTenant')->id);
        $this->assertEquals($tenantBeta->id, session('active_tenant_id'));
    }

    /**
     * Test when accessed via domain (tantou.test:8085), subdomain routing continues to work as expected.
     */
    public function test_subdomain_routing_on_domain_host_continues_to_work(): void
    {
        Edition::fake(true);
        $tenant = Tenant::factory()->create(['slug' => 'acme', 'name' => 'Acme Corp']);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'email' => 'acme.user@tantou.test',
            'password' => 'Tantou#2026!Secure',
        ]);

        // 1. Visiting acme.tantou.test:8085 binds currentTenant to acme
        $response = $this->withServerVariables(['HTTP_HOST' => 'acme.tantou.test:8085', 'SERVER_PORT' => 8085])
            ->get('http://acme.tantou.test:8085/login');

        $response->assertStatus(200);
        $this->assertTrue(app()->bound('currentTenant'));
        $this->assertEquals($tenant->id, app('currentTenant')->id);

        // 2. Logging in on central domain redirects to signed autologin on acme.tantou.test:8085
        $this->withServerVariables(['HTTP_HOST' => 'tantou.test:8085', 'SERVER_PORT' => 8085]);

        Livewire::test(Login::class)
            ->set('email', 'acme.user@tantou.test')
            ->set('password', 'Tantou#2026!Secure')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect();
    }
}
