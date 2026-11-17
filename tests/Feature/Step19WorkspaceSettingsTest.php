<?php

namespace Tests\Feature;

use App\Livewire\Auth\Register;
use App\Livewire\CapacityBoard;
use App\Livewire\TeamManagement;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Step19WorkspaceSettingsTest extends TestCase
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

    public function test_admin_can_update_workspace_name_and_slug(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Original Name',
            'slug' => 'original-slug',
        ]);
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($admin);

        Livewire::test(CapacityBoard::class)
            ->call('openWorkspaceSettingsModal')
            ->assertSet('showWorkspaceSettingsModal', true)
            ->assertSet('tenantName', 'Original Name')
            ->assertSet('tenantSlug', 'original-slug')
            ->set('tenantName', 'Acme Updated')
            ->set('tenantSlug', 'acme-updated')
            ->call('saveWorkspaceSettings')
            ->assertSet('showWorkspaceSettingsModal', false)
            ->assertHasNoErrors();

        $tenant->refresh();
        $this->assertSame('Acme Updated', $tenant->name);
        $this->assertSame('acme-updated', $tenant->slug);
    }

    public function test_non_admin_roles_are_forbidden_from_updating_workspace_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);

        $coordinator = User::factory()->coordinator()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
        ]);
        $worker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
        ]);
        $client = User::factory()->client()->create([
            'tenant_id' => $tenant->id,
        ]);

        foreach ([$coordinator, $worker, $client] as $user) {
            $this->actingAs($user);

            Livewire::test(CapacityBoard::class)
                ->call('openWorkspaceSettingsModal')
                ->assertForbidden();

            Livewire::test(CapacityBoard::class)
                ->call('saveWorkspaceSettings')
                ->assertForbidden();
        }
    }

    public function test_attempting_to_use_existing_slug_fails_validation_and_populates_suggestions(): void
    {
        $tenant1 = Tenant::factory()->create([
            'name' => 'Existing Tenant',
            'slug' => 'acme-hq',
        ]);
        $tenant2 = Tenant::factory()->create([
            'name' => 'Current Tenant',
            'slug' => 'current-slug',
        ]);
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant2->id]);

        $this->actingAs($admin);

        Livewire::test(CapacityBoard::class)
            ->call('openWorkspaceSettingsModal')
            ->set('tenantSlug', 'acme-hq')
            ->assertSet('tenantSlug', 'acme-hq')
            ->assertCount('suggestedSlugs', 3)
            ->call('saveWorkspaceSettings')
            ->assertHasErrors(['tenantSlug'])
            ->assertCount('suggestedSlugs', 3);

        // Verify none of the suggested slugs match the taken slug or reserved list
        $component = Livewire::test(CapacityBoard::class)
            ->call('openWorkspaceSettingsModal')
            ->set('tenantSlug', 'acme-hq');

        $suggestions = $component->get('suggestedSlugs');
        $this->assertCount(3, $suggestions);
        $this->assertNotContains('acme-hq', $suggestions);
        foreach ($suggestions as $slug) {
            $this->assertNotContains($slug, Edition::RESERVED_SLUGS);
            $this->assertFalse(Tenant::where('slug', $slug)->exists());
        }

        // Selecting a suggestion clears errors and suggestions
        $firstSuggestion = $suggestions[0];
        $component->call('selectSuggestedSlug', $firstSuggestion)
            ->assertSet('tenantSlug', $firstSuggestion)
            ->assertSet('suggestedSlugs', [])
            ->call('saveWorkspaceSettings')
            ->assertHasNoErrors();

        $tenant2->refresh();
        $this->assertSame($firstSuggestion, $tenant2->slug);
    }

    public function test_cloud_mode_redirects_to_new_subdomain_signed_autologin_when_slug_changes(): void
    {
        Edition::fake(true);

        $tenant = Tenant::factory()->create([
            'name' => 'Cloud Tenant',
            'slug' => 'old-cloud-slug',
        ]);
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($admin);

        $test = Livewire::test(CapacityBoard::class)
            ->call('openWorkspaceSettingsModal')
            ->set('tenantName', 'Cloud Tenant Updated')
            ->set('tenantSlug', 'new-cloud-slug')
            ->call('saveWorkspaceSettings');

        $tenant->refresh();
        $this->assertSame('new-cloud-slug', $tenant->slug);

        // Verify redirect to signed autologin route with the new slug
        $test->assertRedirect();
        $redirectUrl = $test->effects['redirect'] ?? '';
        $this->assertStringContainsString('new-cloud-slug', $redirectUrl);
        $this->assertStringContainsString('/cloud/autologin/', $redirectUrl);
    }

    public function test_register_suggests_available_slugs_when_slug_is_already_taken_in_cloud_mode(): void
    {
        Edition::fake(true);

        Tenant::factory()->create([
            'slug' => 'taken-slug',
        ]);

        $test = Livewire::test(Register::class)
            ->set('organizationName', 'Taken Slug')
            ->assertSet('slug', 'taken-slug')
            ->assertCount('suggestedSlugs', 3);

        $suggestions = $test->get('suggestedSlugs');
        $this->assertNotContains('taken-slug', $suggestions);

        $test->call('selectSuggestedSlug', $suggestions[0])
            ->assertSet('slug', $suggestions[0])
            ->assertSet('suggestedSlugs', []);
    }

    public function test_team_management_also_exposes_workspace_settings_modal_for_admin(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Team Tenant',
            'slug' => 'team-tenant-slug',
        ]);
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($admin);

        Livewire::test(TeamManagement::class)
            ->call('openWorkspaceSettingsModal')
            ->assertSet('showWorkspaceSettingsModal', true)
            ->assertSeeHtml(__('Workspace Settings'))
            ->set('tenantName', 'Team Tenant Renamed')
            ->call('saveWorkspaceSettings')
            ->assertHasNoErrors();

        $tenant->refresh();
        $this->assertSame('Team Tenant Renamed', $tenant->name);
    }
}
