<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class EditionArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.base_domain', 'localhost');
    }

    protected function tearDown(): void
    {
        Edition::fake(null);
        parent::tearDown();
    }

    /**
     * Test self-hosted mode allows initial organization registration when 0 tenants exist,
     * and strictly locks registration once the first tenant exists.
     */
    public function test_self_hosted_edition_locks_registration_after_first_tenant(): void
    {
        Edition::fake(false);
        $this->assertTrue(Edition::isSelfHosted());
        $this->assertFalse(Edition::isCloud());

        // 1. Zero tenants exist: registration is allowed
        $this->assertEquals(0, Tenant::count());
        $this->assertTrue(Edition::canRegisterNewTenant());

        $this->get('/register')
            ->assertStatus(200)
            ->assertSee('Initial Organization Setup (1/1)');

        $this->get('/')
            ->assertStatus(200)
            ->assertSee('Complete Initial Organization Setup');

        $this->get('/login')
            ->assertStatus(200)
            ->assertSee('Complete Initial Organization Setup');

        // Complete the initial organization registration
        Livewire::test(Register::class)
            ->set('organizationName', 'Self Hosted HQ')
            ->set('name', 'Founder Admin')
            ->set('email', 'founder@selfhosted.local')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertRedirect(route('board'));

        $this->assertEquals(1, Tenant::count());
        $this->assertFalse(Edition::canRegisterNewTenant());

        // 2. Now that 1 tenant exists, /register is locked and redirects to /login
        Auth::logout();

        $this->get('/register')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('This self-hosted workspace is already initialized. Please sign in.'));

        // Calling register action directly also redirects
        Livewire::test(Register::class)
            ->assertRedirect(route('login'));

        // Landing page and login page no longer show registration CTAs
        $this->get('/')
            ->assertDontSee(route('register'));

        $this->get('/login')
            ->assertDontSee('create a new organization workspace');
    }

    /**
     * Test Cloud mode allows multiple registrations with custom subdomains,
     * live preview, and redirects to signed autologin on the tenant subdomain.
     */
    public function test_cloud_edition_allows_registration_with_subdomain_and_autologin_signed_redirect(): void
    {
        Edition::fake(true);
        $this->assertTrue(Edition::isCloud());
        $this->assertFalse(Edition::isSelfHosted());

        // Even with an existing tenant, Cloud mode allows registration
        Tenant::factory()->create(['slug' => 'existing-co']);
        $this->assertTrue(Edition::canRegisterNewTenant());

        Livewire::test(Register::class)
            ->assertSee('Workspace Subdomain')
            ->assertSee('Live Preview:')
            ->set('organizationName', 'Starlight Logistics')
            ->assertSet('slug', 'starlight-logistics')
            ->set('name', 'Elena Admin')
            ->set('email', 'elena@starlight.local')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertRedirect();

        $tenant = Tenant::where('slug', 'starlight-logistics')->first();
        $this->assertNotNull($tenant);
        $this->assertEquals('Starlight Logistics', $tenant->name);

        $admin = User::where('email', 'elena@starlight.local')->first();
        $this->assertNotNull($admin);
        $this->assertEquals($tenant->id, $admin->tenant_id);
    }

    /**
     * Test Cloud mode rejects reserved subdomain slugs and duplicates.
     */
    public function test_cloud_registration_rejects_reserved_subdomains_and_duplicates(): void
    {
        Edition::fake(true);

        Tenant::factory()->create(['slug' => 'taken-slug']);

        // 1. Reserved slugs: admin, www, api, etc.
        foreach (['admin', 'www', 'api', 'billing', 'auth', 'login', 'register'] as $reservedSlug) {
            Livewire::test(Register::class)
                ->set('organizationName', 'Test Corp')
                ->set('slug', $reservedSlug)
                ->set('name', 'Admin User')
                ->set('email', "admin-{$reservedSlug}@example.com")
                ->set('password', 'password123')
                ->set('password_confirmation', 'password123')
                ->call('register')
                ->assertHasErrors(['slug']);
        }

        // 2. Existing duplicate slug
        Livewire::test(Register::class)
            ->set('organizationName', 'Taken Org')
            ->set('slug', 'taken-slug')
            ->set('name', 'Admin User')
            ->set('email', 'admin-taken@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertHasErrors(['slug']);
    }

    /**
     * Test Cloud mode subdomain resolution, cross-tenant login prevention,
     * and automatic central domain redirection.
     */
    public function test_cloud_subdomain_resolution_and_cross_tenant_isolation(): void
    {
        Edition::fake(true);

        $tenantAlpha = Tenant::factory()->create(['slug' => 'alpha', 'name' => 'Alpha Corp']);
        $tenantBeta = Tenant::factory()->create(['slug' => 'beta', 'name' => 'Beta Corp']);

        $deptAlpha = Department::factory()->create(['tenant_id' => $tenantAlpha->id]);
        $deptBeta = Department::factory()->create(['tenant_id' => $tenantBeta->id]);

        $userAlpha = User::factory()->worker()->create([
            'tenant_id' => $tenantAlpha->id,
            'department_id' => $deptAlpha->id,
            'email' => 'alpha.user@example.com',
            'password' => 'secret123',
        ]);

        $userBeta = User::factory()->worker()->create([
            'tenant_id' => $tenantBeta->id,
            'department_id' => $deptBeta->id,
            'email' => 'beta.user@example.com',
            'password' => 'secret123',
        ]);

        // 1. Subdomain tenant resolution: visiting alpha.localhost binds currentTenant
        $response = $this->get('http://alpha.localhost/login');
        $response->assertStatus(200);
        $this->assertTrue(app()->bound('currentTenant'));
        $this->assertEquals($tenantAlpha->id, app('currentTenant')->id);
        $response->assertSee('alpha.localhost');

        // 2. Cross-tenant login restriction: User Beta cannot authenticate on alpha.localhost
        $this->withServerVariables(['HTTP_HOST' => 'alpha.localhost']);
        Livewire::test(Login::class)
            ->set('email', 'beta.user@example.com')
            ->set('password', 'secret123')
            ->call('authenticate')
            ->assertHasErrors(['email']);

        $this->assertGuest();

        // 3. User Alpha CAN authenticate on alpha.localhost
        Livewire::test(Login::class)
            ->set('email', 'alpha.user@example.com')
            ->set('password', 'secret123')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect('/board');

        $this->assertAuthenticatedAs($userAlpha);
        Auth::logout();

        // 4. If User Beta visits alpha.localhost/board, redirected to signed autologin on beta.localhost
        $this->actingAs($userBeta);
        $redirectResponse = $this->get('http://alpha.localhost/board');
        $this->assertStringStartsWith("http://beta.localhost/cloud/autologin/{$userBeta->id}", $redirectResponse->headers->get('Location'));

        $followResponse = $this->get($redirectResponse->headers->get('Location'));
        $followResponse->assertRedirect(route('board'));
        Auth::logout();

        // 5. Visiting /register on a subdomain redirects to central domain /register
        $subdomainRegister = $this->get('http://alpha.localhost/register');
        $subdomainRegister->assertRedirect('http://localhost/register');

        // 6. Authenticated user visiting /board, /team, or /login on central domain (localhost) redirects to signed autologin on subdomain
        $this->actingAs($userAlpha);
        $centralBoardResponse = $this->get('http://localhost/board');
        $this->assertStringStartsWith("http://alpha.localhost/cloud/autologin/{$userAlpha->id}", $centralBoardResponse->headers->get('Location'));

        $centralTeamResponse = $this->get('http://localhost/team');
        $this->assertStringStartsWith("http://alpha.localhost/cloud/autologin/{$userAlpha->id}", $centralTeamResponse->headers->get('Location'));

        $centralLoginResponse = $this->get('http://localhost/login');
        $this->assertStringStartsWith("http://alpha.localhost/cloud/autologin/{$userAlpha->id}", $centralLoginResponse->headers->get('Location'));
        Auth::logout();

        // 7. Logging in on the central domain in Cloud mode redirects to signed autologin on tenant subdomain
        $this->withServerVariables(['HTTP_HOST' => 'localhost']);
        Livewire::test(Login::class)
            ->set('email', 'alpha.user@example.com')
            ->set('password', 'secret123')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect();
    }

    /**
     * Test Edition::tenantUrl helper constructs accurate full URLs with ports and paths.
     */
    public function test_edition_tenant_url_helper_constructs_accurate_urls(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'delta']);

        // Standard port 80 request
        $standardRequest = Request::create('http://localhost/login');
        $this->assertEquals('http://delta.localhost/board', Edition::tenantUrl($tenant, '/board', $standardRequest));
        $this->assertEquals('http://delta.localhost/team', Edition::tenantUrl($tenant, '/team', $standardRequest));
        $this->assertEquals('http://delta.localhost', Edition::tenantUrl($tenant, '', $standardRequest));

        // Custom port request (e.g., :8085)
        $customPortRequest = Request::create('http://localhost:8085/login');
        $this->assertEquals('http://delta.localhost:8085/board', Edition::tenantUrl($tenant, '/board', $customPortRequest));
        $this->assertEquals('http://delta.localhost:8085/team', Edition::tenantUrl($tenant, '/team', $customPortRequest));
        $this->assertEquals('http://delta.localhost:8085', Edition::tenantUrl($tenant, '', $customPortRequest));
    }

    /**
     * Test SubdomainAutoLoginController requires a valid signature and logs in the user.
     */
    public function test_cloud_autologin_signed_route_verification_and_authentication(): void
    {
        Edition::fake(true);

        $tenant = Tenant::factory()->create(['slug' => 'gamma', 'name' => 'Gamma Corp']);
        $user = User::factory()->worker()->create(['tenant_id' => $tenant->id]);

        // 1. Unsigned request fails with 401
        $unsignedResponse = $this->get("http://gamma.localhost/cloud/autologin/{$user->id}");
        $unsignedResponse->assertStatus(401);
        $this->assertGuest();

        // 2. Valid signed request on tenant subdomain logs in and redirects to /board
        URL::forceRootUrl('http://gamma.localhost');
        $signedUrl = URL::temporarySignedRoute(
            'cloud.autologin',
            now()->addMinutes(15),
            ['user' => $user->id]
        );
        URL::forceRootUrl(null);

        $signedResponse = $this->get($signedUrl);
        $signedResponse->assertRedirect(route('board'));
        $this->assertAuthenticatedAs($user);
    }
}
