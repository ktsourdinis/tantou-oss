<?php

namespace Tests\Feature;

use App\Livewire\Auth\CompleteRegistration;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\RegisterTenant;
use App\Livewire\PublicSubmitRequest;
use App\Mail\VerifyRegistrationMagicLinkMail;
use App\Models\Department;
use App\Models\PendingRegistration;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityAndRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('magic-reg|127.0.0.1|newadmin@example.com');
        RateLimiter::clear('badlogin@example.com|127.0.0.1');
        RateLimiter::clear('public-submit|127.0.0.1');
        RateLimiter::clear('public-track|127.0.0.1');
    }

    /**
     * 1. Test /register with email creates a PendingRegistration (NOT a User or Tenant yet) and sends magic link.
     */
    public function test_register_creates_pending_registration_and_sends_magic_link(): void
    {
        Edition::fake(false);
        Mail::fake();

        Livewire::test(RegisterTenant::class)
            ->set('email', 'newadmin@example.com')
            ->call('requestMagicLink')
            ->assertHasNoErrors()
            ->assertSee('Check your inbox');

        // Verify PendingRegistration was created
        $this->assertDatabaseHas('pending_registrations', [
            'email' => 'newadmin@example.com',
        ]);

        $pending = PendingRegistration::where('email', 'newadmin@example.com')->first();
        $this->assertNotNull($pending);
        $this->assertTrue($pending->expires_at->isFuture());

        // Verify NO Tenant or User has been created yet!
        $this->assertDatabaseMissing('users', ['email' => 'newadmin@example.com']);
        $this->assertDatabaseCount('tenants', 0);

        // Verify Mail was sent
        Mail::assertSent(VerifyRegistrationMagicLinkMail::class, function ($mail) {
            return $mail->emailAddress === 'newadmin@example.com';
        });

        Edition::fake(null);
    }

    /**
     * 2. Test expired PendingRegistration records are pruned and rejected on /register/complete/{token}.
     */
    public function test_expired_pending_registrations_are_pruned_and_rejected(): void
    {
        Edition::fake(false);

        $plainToken = Str::random(64);
        $expired = PendingRegistration::create([
            'email' => 'expired@example.com',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->subMinutes(5),
        ]);

        // Command prunes expired record
        $this->artisan('tantou:prune-pending-registrations')
            ->assertSuccessful();

        $this->assertDatabaseMissing('pending_registrations', [
            'id' => $expired->id,
        ]);

        // Attempting to complete with expired / non-existent token shows error
        Livewire::test(CompleteRegistration::class, ['token' => $plainToken])
            ->assertSee('Invalid or Expired Link')
            ->assertDontSee('Launch Organization Workspace');

        Edition::fake(null);
    }

    /**
     * 3. Test strong password rules are enforced on registration completion.
     */
    public function test_complete_registration_enforces_strong_password_rules(): void
    {
        Edition::fake(false);

        $plainToken = Str::random(64);
        $pending = PendingRegistration::create([
            'email' => 'founder@acme.corp',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(25),
        ]);

        // 3a. Weak password (too short, no symbols/mixed case): rejected
        Livewire::test(CompleteRegistration::class, ['token' => $plainToken])
            ->set('organization_name', 'Acme Corp')
            ->set('name', 'Acme Founder')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('complete')
            ->assertHasErrors(['password']);

        // 3b. Strong password (min 10 chars, upper, lower, number, symbol): accepted!
        Livewire::test(CompleteRegistration::class, ['token' => $plainToken])
            ->set('organization_name', 'Acme Corp')
            ->set('name', 'Acme Founder')
            ->set('password', 'Tantou#2026!Secure')
            ->set('password_confirmation', 'Tantou#2026!Secure')
            ->set('plan', 'cloud')
            ->call('complete')
            ->assertHasNoErrors()
            ->assertRedirect('/board');

        // Tenant and Admin user created
        $this->assertDatabaseHas('tenants', ['name' => 'Acme Corp']);
        $tenant = Tenant::where('name', 'Acme Corp')->first();
        $this->assertNotNull($tenant);

        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->id,
            'email' => 'founder@acme.corp',
            'role' => 'admin',
        ]);

        $admin = User::where('email', 'founder@acme.corp')->first();
        $this->assertNotNull($admin->email_verified_at);

        // Pending registration deleted immediately upon completion
        $this->assertDatabaseMissing('pending_registrations', [
            'id' => $pending->id,
        ]);

        $this->assertAuthenticatedAs($admin);

        // 3c. Cloud Mode CompleteRegistration offers Tantou Cloud Managed as sole self-service plan,
        // displays Enterprise mailto CTA to tantou@tsourdinis.info, and redirects cleanly to route('board', ['workspace' => slug])
        auth()->logout();
        Edition::fake(true);

        $cloudToken = Str::random(64);
        PendingRegistration::create([
            'email' => 'director@hospital.eu',
            'token_hash' => hash('sha256', $cloudToken),
            'expires_at' => now()->addMinutes(25),
        ]);

        // Enterprise self-service plan is rejected in validation
        Livewire::test(CompleteRegistration::class, ['token' => $cloudToken])
            ->assertSee('Tantou Cloud Managed')
            ->assertSee('mailto:tantou@tsourdinis.info?subject=Tantou%20Enterprise%20White-Label%20Inquiry', false)
            ->assertSee('Need White-Label or Public Sector Procurement? Contact tantou@tsourdinis.info →')
            ->set('organization_name', 'General Hospital IT')
            ->set('slug', 'general-hospital-it')
            ->set('name', 'Dr. Helena')
            ->set('password', 'Hospital#2026!Secure')
            ->set('password_confirmation', 'Hospital#2026!Secure')
            ->set('plan', 'enterprise')
            ->call('complete')
            ->assertHasErrors(['plan']);

        // Completing under cloud_managed provisions 14-day trial and redirects to route('board', ['workspace' => slug])
        Livewire::test(CompleteRegistration::class, ['token' => $cloudToken])
            ->set('organization_name', 'General Hospital IT')
            ->set('slug', 'general-hospital-it')
            ->set('name', 'Dr. Helena')
            ->set('password', 'Hospital#2026!Secure')
            ->set('password_confirmation', 'Hospital#2026!Secure')
            ->set('plan', 'cloud_managed')
            ->set('billing_interval', 'yearly')
            ->call('complete')
            ->assertHasNoErrors()
            ->assertRedirect(route('board', ['workspace' => 'general-hospital-it']));

        $hospitalTenant = Tenant::where('slug', 'general-hospital-it')->first();
        $this->assertNotNull($hospitalTenant);
        $this->assertSame('cloud_managed', $hospitalTenant->plan);
        $this->assertSame('yearly', $hospitalTenant->billing_interval);
        $this->assertSame('trialing', $hospitalTenant->subscription_status);
        $this->assertNotNull($hospitalTenant->trial_ends_at);
        $this->assertTrue($hospitalTenant->trial_ends_at->isFuture());
        $this->assertTrue($hospitalTenant->hasActiveSubscription());
        $this->assertFalse($hospitalTenant->isTrialExpired());
        $this->assertSame(1, $hospitalTenant->activeWorkersCount());
        $this->assertTrue($hospitalTenant->canUseFeature('strict_b2b_isolation'));
        $this->assertTrue($hospitalTenant->canUseFeature('gdpr_privacy_mode'));
        $this->assertTrue($hospitalTenant->canUseFeature('custom_telegram_bot'));

        // When trial_ends_at passes without a Stripe subscription, cloud_managed tenant is locked
        $hospitalTenant->update(['trial_ends_at' => now()->subDay()]);
        $this->assertTrue($hospitalTenant->fresh()->isTrialExpired());
        $this->assertFalse($hospitalTenant->fresh()->hasActiveSubscription());
        $this->assertFalse($hospitalTenant->fresh()->canAddWorker());
        $this->assertFalse($hospitalTenant->fresh()->canUseFeature('strict_b2b_isolation'));

        // Master Superadmin workspace (tantou-team) and demo workspace (hellenic-demo-ops) bypass trial expiration locks
        $masterTenant = Tenant::factory()->create([
            'slug' => 'tantou-team',
            'name' => 'Tantou Team',
            'plan' => 'cloud_managed',
            'subscription_status' => 'trialing',
            'trial_ends_at' => now()->subDays(30),
        ]);
        $demoTenant = Tenant::factory()->create([
            'slug' => 'hellenic-demo-ops',
            'name' => 'Hellenic Demo Ops',
            'plan' => 'cloud_managed',
            'subscription_status' => 'trialing',
            'trial_ends_at' => now()->subDays(30),
        ]);

        $this->assertFalse($masterTenant->isTrialExpired());
        $this->assertTrue($masterTenant->hasActiveSubscription());
        $this->assertTrue($masterTenant->canAddWorker());
        $this->assertFalse($demoTenant->isTrialExpired());
        $this->assertTrue($demoTenant->hasActiveSubscription());
        $this->assertTrue($demoTenant->canAddWorker());

        Edition::fake(null);
    }

    /**
     * 4. Test login rate limiting blocks after 5 consecutive failed attempts.
     */
    public function test_login_rate_limiting_blocks_after_five_failed_attempts(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'badlogin@example.com',
            'password' => 'Tantou#2026!Secure',
        ]);

        $component = Livewire::test(Login::class)
            ->set('email', 'badlogin@example.com')
            ->set('password', 'wrong-pass');

        // Attempts 1 to 5 fail normally
        for ($i = 1; $i <= 5; $i++) {
            $component->set('password', 'wrong-pass')
                ->call('authenticate')
                ->assertHasErrors(['email']);
        }

        // 6th attempt is throttled and blocked with remaining seconds
        $component->set('password', 'wrong-pass')
            ->call('authenticate')
            ->assertSee('Too many login attempts. Please try again in');

        $this->assertGuest();
    }

    /**
     * 5. Test strict security headers are attached to responses.
     */
    public function test_security_headers_are_present_on_responses(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
    }

    /**
     * 6. Test honeypot trap on public request submission and magic link registration.
     */
    public function test_honeypot_traps_spambots(): void
    {
        Edition::fake(false);
        Mail::fake();

        // Honeypot on RegisterTenant
        Livewire::test(RegisterTenant::class)
            ->set('email', 'spambot@example.com')
            ->set('website_url', 'http://spam-link.ru')
            ->call('requestMagicLink');

        // Mail should NOT be sent when honeypot is filled
        Mail::assertNothingSent();
        $this->assertDatabaseMissing('pending_registrations', ['email' => 'spambot@example.com']);

        // Honeypot on PublicSubmitRequest
        $tenant = Tenant::factory()->create(['public_submission_enabled' => true]);
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);

        Livewire::test(PublicSubmitRequest::class, ['tenantSlug' => $tenant->slug])
            ->set('guest_name', 'Bot')
            ->set('facility_name', 'Spam Unit')
            ->set('department_id', $dept->id)
            ->set('title', 'Spam Task')
            ->set('website_url', 'http://spam.com')
            ->call('submit')
            ->assertHasErrors(['title']);

        $this->assertDatabaseMissing('post_its', ['title' => 'Spam Task']);

        Edition::fake(null);
    }

    /**
     * 7. Test executable file uploads are strictly rejected on PublicSubmitRequest.
     */
    public function test_executable_file_uploads_are_rejected(): void
    {
        Storage::fake('local');
        $tenant = Tenant::factory()->create(['public_submission_enabled' => true]);
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);

        $maliciousFile = UploadedFile::fake()->create('exploit.php', 10, 'application/x-php');

        Livewire::test(PublicSubmitRequest::class, ['tenantSlug' => $tenant->slug])
            ->set('guest_name', 'Attacker')
            ->set('facility_name', 'External')
            ->set('department_id', $dept->id)
            ->set('title', 'Exploit attempt')
            ->set('attachments', [$maliciousFile])
            ->call('submit')
            ->assertHasErrors(['attachments.0']);

        $this->assertDatabaseMissing('post_its', ['title' => 'Exploit attempt']);
    }
}
