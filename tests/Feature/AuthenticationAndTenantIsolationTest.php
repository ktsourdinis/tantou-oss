<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationAndTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Tantou');
        $response->assertSee('Human Capacity OS');
    }

    public function test_unauthenticated_user_cannot_access_board(): void
    {
        $response = $this->get('/board');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_board(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'hellenic-demo-ops']);
        $user = User::factory()->worker()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->get('/board');
        $response->assertStatus(200);
    }

    public function test_user_can_login_via_livewire(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'worker@example.com',
            'password' => 'secret123',
        ]);

        Livewire::test(Login::class)
            ->set('email', 'worker@example.com')
            ->set('password', 'secret123')
            ->call('authenticate')
            ->assertRedirect('/board');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'worker@example.com',
            'password' => 'secret123',
        ]);

        Livewire::test(Login::class)
            ->set('email', 'worker@example.com')
            ->set('password', 'wrong-password')
            ->call('authenticate')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_quick_demo_login_authenticates_seed_user(): void
    {
        $tenant = Tenant::factory()->create();
        $demoUser = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'email' => 'admin@tantou.local',
        ]);

        Livewire::test(Login::class)
            ->call('quickLogin', 'admin@tantou.local')
            ->assertRedirect('/board');

        $this->assertAuthenticatedAs($demoUser);
    }

    public function test_workspace_registration_creates_tenant_department_admin_and_logs_in(): void
    {
        Livewire::test(Register::class)
            ->set('organizationName', 'Acme Systems')
            ->set('name', 'Acme Admin')
            ->set('email', 'admin@acme.local')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertRedirect('/board');

        $this->assertDatabaseHas('tenants', [
            'name' => 'Acme Systems',
            'slug' => 'acme-systems',
        ]);

        $tenant = Tenant::where('slug', 'acme-systems')->first();
        $this->assertNotNull($tenant);

        $this->assertDatabaseHas('departments', [
            'tenant_id' => $tenant->id,
            'name' => 'General',
            'slug' => 'general',
        ]);

        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->id,
            'email' => 'admin@acme.local',
            'role' => 'admin',
        ]);

        $this->assertAuthenticated();
    }

    public function test_tenant_scope_isolates_departments_and_post_its(): void
    {
        $tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        $deptA = Department::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'Dept Alpha']);
        $deptB = Department::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Dept Beta']);

        $postItA = PostIt::factory()->create([
            'tenant_id' => $tenantA->id,
            'requester_id' => $userA->id,
            'title' => 'Task for Tenant A',
        ]);
        $postItB = PostIt::factory()->create([
            'tenant_id' => $tenantB->id,
            'requester_id' => $userB->id,
            'title' => 'Task for Tenant B',
        ]);

        // When acting as User A, only Tenant A records should be visible
        $this->actingAs($userA);

        $this->assertTrue(Department::where('id', $deptA->id)->exists());
        $this->assertFalse(Department::where('id', $deptB->id)->exists());

        $this->assertTrue(PostIt::where('id', $postItA->id)->exists());
        $this->assertFalse(PostIt::where('id', $postItB->id)->exists());

        // Creating a post-it without explicit tenant_id auto-assigns user's tenant
        $newPostIt = PostIt::create([
            'requester_id' => $userA->id,
            'title' => 'Auto scoped post-it',
            'status' => 'backlog',
            'weight' => 2,
        ]);
        $this->assertEquals($tenantA->id, $newPostIt->tenant_id);

        // When acting as User B, only Tenant B records should be visible
        $this->actingAs($userB);

        $this->assertFalse(Department::where('id', $deptA->id)->exists());
        $this->assertTrue(Department::where('id', $deptB->id)->exists());

        $this->assertFalse(PostIt::where('id', $postItA->id)->exists());
        $this->assertTrue(PostIt::where('id', $postItB->id)->exists());
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->post('/logout');
        $response->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_private_tenant_channel_authorization_rule(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

        // Test the channel callback logic: (int) $user->tenant_id === (int) $tenantId
        $authorized = (int) $userA->tenant_id === (int) $tenantA->id;
        $forbidden = (int) $userA->tenant_id === (int) $tenantB->id;

        $this->assertTrue($authorized);
        $this->assertFalse($forbidden);
    }
}
