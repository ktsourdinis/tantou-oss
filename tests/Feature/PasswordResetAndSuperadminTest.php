<?php

namespace Tests\Feature;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\SuperadminDashboard;
use App\Mail\ResetPasswordMail;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordResetAndSuperadminTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Department $department;

    protected User $regularAdmin;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        Edition::fake(true);

        $this->tenant = Tenant::factory()->create([
            'name' => 'Hellenic Operations',
            'slug' => 'hellenic-ops',
            'plan' => 'team',
            'subscription_status' => 'active',
        ]);

        $this->department = Department::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Operations',
            'color' => '#6366f1',
        ]);

        $this->regularAdmin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Regular Admin',
            'email' => 'regular-admin@hellenic-ops.gr',
            'is_superadmin' => false,
        ]);

        $this->superAdmin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Platform Superadmin',
            'email' => 'platform-admin@tantou-cloud.test',
            'is_superadmin' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Edition::fake(null);
        parent::tearDown();
    }

    /**
     * 1. Requesting a password reset link on /forgot-password generates a token
     * and resetting the password on /reset-password/{token} updates the password and allows login.
     */
    public function test_password_reset_flow_generates_token_and_resets_password(): void
    {
        Mail::fake();

        $member = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'email' => 'worker@hellenic-ops.gr',
            'password' => Hash::make('OldPassword123!#'),
        ]);

        // A. Request password reset via ForgotPassword component
        Livewire::test(ForgotPassword::class)
            ->set('email', 'worker@hellenic-ops.gr')
            ->call('sendResetLink')
            ->assertHasNoErrors()
            ->assertSet('linkSent', true);

        // Assert token created in password_reset_tokens
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', 'worker@hellenic-ops.gr')
            ->first();

        $this->assertNotNull($resetRecord);
        $this->assertNotNull($resetRecord->token);

        // Assert mail was sent
        Mail::assertSent(ResetPasswordMail::class, function ($mail) {
            return $mail->hasTo('worker@hellenic-ops.gr');
        });

        // B. Generate known token to verify ResetPassword component
        $plainToken = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => 'worker@hellenic-ops.gr'],
            [
                'token' => Hash::make($plainToken),
                'created_at' => now(),
            ]
        );

        Livewire::test(ResetPassword::class, ['token' => $plainToken])
            ->set('email', 'worker@hellenic-ops.gr')
            ->set('password', 'StrongNewPassword123!#')
            ->set('password_confirmation', 'StrongNewPassword123!#')
            ->call('resetPassword')
            ->assertHasNoErrors();

        // Assert password has been updated
        $member->refresh();
        $this->assertTrue(Hash::check('StrongNewPassword123!#', $member->password));

        // Assert token is purged
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'worker@hellenic-ops.gr',
        ]);

        // Assert user is logged in
        $this->assertAuthenticatedAs($member);
    }

    /**
     * 2. Regular tenant admins (is_superadmin = false) receive 403 Forbidden when visiting /superadmin.
     */
    public function test_regular_tenant_admins_receive_403_forbidden_on_superadmin(): void
    {
        // Guest is redirected to login
        $this->get('/superadmin')->assertRedirect('/login');

        // Regular tenant admin receives 403
        $this->actingAs($this->regularAdmin)
            ->get('/superadmin')
            ->assertForbidden();
    }

    /**
     * 3. A Superadmin (is_superadmin = true) can view all tenants across the platform,
     * change a tenant's plan from team to business, impersonate a tenant admin, and return cleanly to /superadmin.
     */
    public function test_superadmin_can_view_all_tenants_change_plan_impersonate_and_return(): void
    {
        // Create second tenant with its own admin
        $secondTenant = Tenant::factory()->create([
            'name' => 'Aegean Marine',
            'slug' => 'aegean-marine',
            'plan' => 'team',
            'subscription_status' => 'active',
        ]);

        $secondAdmin = User::factory()->admin()->create([
            'tenant_id' => $secondTenant->id,
            'name' => 'Nikos Captain',
            'email' => 'nikos@aegean-marine.gr',
            'is_superadmin' => false,
        ]);

        // Superadmin visits /superadmin successfully
        $this->actingAs($this->superAdmin)
            ->get('/superadmin')
            ->assertOk()
            ->assertSee('Hellenic Operations')
            ->assertSee('Aegean Marine');

        // Livewire tests: Change plan from team to business
        Livewire::actingAs($this->superAdmin)
            ->test(SuperadminDashboard::class)
            ->call('openEditTenantModal', $secondTenant->id)
            ->assertSet('editingPlan', 'team')
            ->set('editingPlan', 'business')
            ->set('editingStatus', 'active')
            ->set('editingCustomDomain', 'marine.aegean.gr')
            ->call('saveTenantEdits')
            ->assertHasNoErrors()
            ->assertSet('showEditModal', false);

        $secondTenant->refresh();
        $this->assertEquals('business', $secondTenant->plan);
        $this->assertEquals('marine.aegean.gr', $secondTenant->custom_domain);

        // Livewire test: Impersonate second tenant admin
        Livewire::actingAs($this->superAdmin)
            ->test(SuperadminDashboard::class)
            ->call('impersonate', $secondTenant->id)
            ->assertRedirect();

        // Verify session and auth state after impersonation
        $this->assertEquals($secondAdmin->id, Auth::id());
        $this->assertEquals($this->superAdmin->id, session('impersonator_id'));
        $this->assertEquals($secondTenant->id, session('active_tenant_id'));

        // Leave impersonation via POST /superadmin/leave-impersonation
        $this->actingAs($secondAdmin)
            ->withSession([
                'impersonator_id' => $this->superAdmin->id,
                'active_tenant_id' => $secondTenant->id,
            ])
            ->post('/superadmin/leave-impersonation')
            ->assertRedirect(route('superadmin.dashboard'));

        // Original superadmin session is restored
        $this->assertEquals($this->superAdmin->id, Auth::id());
        $this->assertFalse(session()->has('impersonator_id'));
    }

    /**
     * 4. Artisan CLI command toggles superadmin flag.
     */
    public function test_make_superadmin_cli_command_toggles_flag(): void
    {
        $testUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'promote-me@test.gr',
            'is_superadmin' => false,
        ]);

        $this->artisan('tantou:make-superadmin promote-me@test.gr')
            ->assertSuccessful();

        $this->assertTrue($testUser->fresh()->is_superadmin);

        $this->artisan('tantou:make-superadmin promote-me@test.gr --revoke')
            ->assertSuccessful();

        $this->assertFalse($testUser->fresh()->is_superadmin);
    }

    /**
     * 5. Superadmin can provision new organization workspace from dashboard.
     */
    public function test_superadmin_can_provision_new_tenant_from_dashboard(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(SuperadminDashboard::class)
            ->call('openCreateTenantModal')
            ->set('newTenantName', 'Crete Software Ops')
            ->set('newTenantSlug', 'crete-ops')
            ->set('newTenantPlan', 'business')
            ->set('newTenantStatus', 'active')
            ->set('newAdminName', 'Maria Kretan')
            ->set('newAdminEmail', 'maria@crete-ops.gr')
            ->set('newAdminPassword', 'SuperSecurePass123!#')
            ->set('newAdminPassword_confirmation', 'SuperSecurePass123!#')
            ->call('createTenant')
            ->assertHasNoErrors()
            ->assertSet('showCreateModal', false);

        $newTenant = Tenant::where('slug', 'crete-ops')->first();
        $this->assertNotNull($newTenant);
        $this->assertEquals('Crete Software Ops', $newTenant->name);
        $this->assertEquals('business', $newTenant->plan);

        $newAdmin = User::withoutGlobalScopes()->where('email', 'maria@crete-ops.gr')->first();
        $this->assertNotNull($newAdmin);
        $this->assertEquals('Maria Kretan', $newAdmin->name);
        $this->assertEquals('admin', $newAdmin->role);
        $this->assertEquals($newTenant->id, $newAdmin->tenant_id);
    }

    /**
     * 6. In community edition, /superadmin is locked and returns 404 even for superadmin users.
     */
    public function test_superadmin_dashboard_returns_404_when_in_community_edition(): void
    {
        Edition::fake(false);

        $this->actingAs($this->superAdmin)
            ->get('/superadmin')
            ->assertNotFound();
    }

    /**
     * 7. Demo admin (admin@tantou.local) can manage /team as Tenant Admin, gets 403 on /superadmin,
     * does not see Superadmin nav button, and superadmin_emails allowlist is strictly enforced.
     */
    public function test_demo_admin_can_manage_team_but_cannot_access_superadmin_and_allowlist_is_enforced(): void
    {
        $this->artisan('tantou:reset-demo')->assertSuccessful();

        // Duplicate alex@hellenic-demo.gr is never seeded
        $this->assertDatabaseMissing('users', ['email' => 'alex@hellenic-demo.gr']);

        $alex = User::withoutGlobalScopes()->where('email', 'admin@tantou.local')->firstOrFail();
        $this->assertSame('admin', $alex->role);
        $this->assertFalse($alex->isSuperAdmin());
        $this->assertFalse($alex->is_superadmin);

        // Even if someone forces the raw DB column to 1 on a @tantou.local or @hellenic-demo.gr user, isSuperAdmin() is false
        DB::table('users')->where('id', $alex->id)->update(['is_superadmin' => 1]);
        $alex->refresh();
        $this->assertFalse($alex->isSuperAdmin());
        $this->assertFalse($alex->is_superadmin);

        // Alex M. can access /team as Tenant Admin and does NOT see Superadmin navigation link
        $this->actingAs($alex)
            ->get('/team')
            ->assertOk()
            ->assertDontSee(route('superadmin.dashboard'));

        // Alex M. receives 403 Forbidden on /superadmin
        $this->actingAs($alex)
            ->get('/superadmin')
            ->assertForbidden();

        // Allowlist enforcement when config('tantou-cloud.superadmin_emails') is non-empty
        config(['tantou-cloud.superadmin_emails' => 'allowed-owner@tantou-cloud.test']);
        $this->assertFalse($this->superAdmin->fresh()->isSuperAdmin());

        config(['tantou-cloud.superadmin_emails' => 'platform-admin@tantou-cloud.test']);
        $this->assertTrue($this->superAdmin->fresh()->isSuperAdmin());

        config(['tantou-cloud.superadmin_emails' => '']);
    }

    /**
     * 8. Seeding with TANTOU_SUPERADMIN_EMAIL and TANTOU_SUPERADMIN_PASSWORD creates "Tantou Team" workspace,
     * sets the hashed password, and grants is_superadmin = true exclusively to that user.
     */
    public function test_seeding_with_master_superadmin_env_provisions_tantou_team_and_exclusive_superadmin(): void
    {
        config([
            'tantou-cloud.superadmin_email' => 'founder@tantou-studio.eu',
            'tantou-cloud.superadmin_password' => 'MasterPass#2026!Strong',
            'tantou-cloud.superadmin_name' => 'Konstantinos Tsourdinis',
            'tantou-cloud.superadmin_workspace' => 'Tantou Team',
            'tantou-cloud.superadmin_slug' => 'tantou-team',
        ]);

        $this->artisan('db:seed')->assertSuccessful();

        $masterWorkspace = Tenant::where('slug', 'tantou-team')->first();
        $this->assertNotNull($masterWorkspace);
        $this->assertSame('Tantou Team', $masterWorkspace->name);
        $this->assertSame('business', $masterWorkspace->plan);
        $this->assertSame('active', $masterWorkspace->subscription_status);
        $this->assertTrue($masterWorkspace->hasActiveSubscription());

        $masterUser = User::withoutGlobalScopes()->where('email', 'founder@tantou-studio.eu')->first();
        $this->assertNotNull($masterUser);
        $this->assertSame('Konstantinos Tsourdinis', $masterUser->name);
        $this->assertSame($masterWorkspace->id, $masterUser->tenant_id);
        $this->assertSame('admin', $masterUser->role);
        $this->assertNotNull($masterUser->email_verified_at);
        $this->assertTrue(Hash::check('MasterPass#2026!Strong', $masterUser->password));
        $this->assertTrue($masterUser->isSuperAdmin());
        $this->assertTrue($masterUser->is_superadmin);

        // Demo admin (admin@tantou.local) has is_superadmin = false
        $alex = User::withoutGlobalScopes()->where('email', 'admin@tantou.local')->firstOrFail();
        $this->assertFalse($alex->isSuperAdmin());
        $this->assertFalse($alex->is_superadmin);

        // Even another user with is_superadmin = 1 in DB is rejected because superadmin_email is exclusively founder@tantou-studio.eu
        $this->assertFalse($this->superAdmin->fresh()->isSuperAdmin());

        config([
            'tantou-cloud.superadmin_email' => '',
            'tantou-cloud.superadmin_password' => '',
        ]);
    }
}
