<?php

namespace Tests\Feature;

use App\Events\BoardUpdated;
use App\Livewire\CapacityBoard;
use App\Livewire\PublicSubmitRequest;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class WorkspaceSettingsAndPublicDropBoxFixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Edition::fake(false);
    }

    protected function tearDown(): void
    {
        Edition::fake(null);
        parent::tearDown();
    }

    public function test_visiting_submit_with_tenant_slug_resolves_requested_tenant_even_when_logged_in_as_another_tenant_user(): void
    {
        Event::fake([BoardUpdated::class]);

        $tenantA = Tenant::factory()->create([
            'name' => 'Hellenic Demo Ops',
            'slug' => 'hellenic-demo-ops',
            'public_submission_enabled' => true,
        ]);
        $userA = User::factory()->admin()->create(['tenant_id' => $tenantA->id]);

        $tenantB = Tenant::factory()->create([
            'name' => 'Norton and Ball Traders',
            'slug' => 'norton-and-ball-traders',
            'public_submission_enabled' => true,
        ]);
        $deptB = Department::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Logistics Support',
            'slug' => 'logistics',
            'color' => '#10b981',
        ]);

        // Act as User A (from Hellenic Demo Ops)
        $this->actingAs($userA);

        // Visit Tenant B's drop box on an intranet IP
        $response = $this->withHeaders(['Host' => '192.168.1.100:8085'])
            ->get('/submit/norton-and-ball-traders');

        $response->assertStatus(200);
        $response->assertSee('Norton and Ball Traders');
        $response->assertDontSee('Hellenic Demo Ops');

        // Test Livewire component submission correctly targets Tenant B
        Livewire::test(PublicSubmitRequest::class, ['tenantSlug' => 'norton-and-ball-traders'])
            ->assertSet('tenantId', $tenantB->id)
            ->set('guest_name', 'Cargo Officer')
            ->set('facility_name', 'Dock 4')
            ->set('department_id', $deptB->id)
            ->set('title', 'Crane 2 Sensor Alert')
            ->call('submit')
            ->assertHasNoErrors();

        $postIt = PostIt::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->first();
        $this->assertNotNull($postIt);
        $this->assertSame('Crane 2 Sensor Alert', $postIt->title);
        $this->assertSame('Dock 4', $postIt->facility_name);
        $this->assertSame($tenantB->id, $postIt->tenant_id);

        // Ensure no post-it was created under Tenant A
        $this->assertDatabaseMissing('post_its', [
            'tenant_id' => $tenantA->id,
            'title' => 'Crane 2 Sensor Alert',
        ]);
    }

    public function test_visiting_submit_with_disabled_tenant_aborts_403(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'disabled-workspace',
            'public_submission_enabled' => false,
        ]);

        $response = $this->get('/submit/disabled-workspace');
        $response->assertStatus(403);
    }

    public function test_visiting_submit_with_non_existent_tenant_aborts_404(): void
    {
        $response = $this->get('/submit/completely-non-existent-workspace-slug');
        $response->assertStatus(404);
    }

    public function test_workspace_settings_modal_renders_compact_tabbed_layout_and_saves_settings(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Initial Company',
            'slug' => 'initial-company',
            'timezone' => 'UTC',
            'telegram_enabled' => false,
        ]);

        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($admin);

        Livewire::test(CapacityBoard::class)
            ->call('openWorkspaceSettingsModal')
            ->assertSet('showWorkspaceSettingsModal', true)
            ->assertSee('General')
            ->assertSee('Access &amp; Drop-Box', false)
            ->assertSee('Telegram Bot')
            ->assertSee('Quick Telegram Setup Guide')
            ->assertSee('php artisan tantou:telegram-poll')
            ->set('publicSubmissionEnabled', true)
            ->assertSee('copyToClipboard')
            ->set('tenantName', 'Updated Acme Co')
            ->set('tenantSlug', 'updated-acme-co')
            ->set('tenantTimezone', 'Europe/Athens')
            ->set('strictClientIsolation', true)
            ->set('telegramEnabled', true)
            ->set('telegramPrivacyMode', true)
            ->set('telegramBotUsername', 'AcmeAlertsBot')
            ->call('saveWorkspaceSettings')
            ->assertHasNoErrors()
            ->assertSet('showWorkspaceSettingsModal', false);

        $tenant->refresh();
        $this->assertSame('Updated Acme Co', $tenant->name);
        $this->assertSame('updated-acme-co', $tenant->slug);
        $this->assertSame('Europe/Athens', $tenant->timezone);
        $this->assertTrue((bool) $tenant->strict_client_isolation);
        $this->assertTrue((bool) $tenant->public_submission_enabled);
        $this->assertTrue((bool) $tenant->telegram_enabled);
        $this->assertTrue((bool) $tenant->telegram_privacy_mode);
        $this->assertSame('AcmeAlertsBot', $tenant->telegram_bot_username);
    }
}
