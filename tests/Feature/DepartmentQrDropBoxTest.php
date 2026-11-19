<?php

namespace Tests\Feature;

use App\Livewire\PublicSubmitRequest;
use App\Livewire\TeamManagement;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DepartmentQrDropBoxTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_department_qr_modal_generates_exact_prefilled_submit_url_and_svg(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Tantou Team',
            'slug' => 'tantou-team',
            'public_submission_enabled' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
            'locale' => 'en',
        ]);

        $department = Department::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Radiology Unit',
            'slug' => 'radiology-unit',
            'color' => '#10b981',
        ]);

        $expectedUrl = route('public.submit', [
            'workspace' => $tenant->slug,
            'facility' => $department->name,
        ]);

        $this->actingAs($admin);

        Livewire::test(TeamManagement::class)
            ->call('setActiveTab', 'departments')
            ->assertSee('QR Drop-Box')
            ->call('openDepartmentQrModal', $department->id)
            ->assertSet('showDepartmentQrModal', true)
            ->assertSet('qrDepartmentName', 'Radiology Unit')
            ->assertSet('qrDepartmentSlug', 'radiology-unit')
            ->assertSet('qrDropBoxUrl', $expectedUrl)
            ->assertSee($expectedUrl)
            ->assertSee('Copy Link')
            ->assertSee('Download SVG')
            ->assertSee('Download PNG')
            ->assertSee('Print QR Poster')
            ->assertSee('Scan with your phone camera to submit an IT / Support request — No login required')
            ->assertSeeHtml('<svg xmlns="http://www.w3.org/2000/svg"')
            ->call('closeDepartmentQrModal')
            ->assertSet('showDepartmentQrModal', false);

        $parsed = parse_url($expectedUrl);
        $this->assertSame('/submit', $parsed['path'] ?? null);

        parse_str($parsed['query'] ?? '', $queryParams);
        $this->assertSame('tantou-team', $queryParams['workspace'] ?? null);
        $this->assertSame('Radiology Unit', $queryParams['facility'] ?? null);
    }

    public function test_department_qr_modal_enforces_tenant_isolation(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'tenant-alpha']);
        $tenantB = Tenant::factory()->create(['slug' => 'tenant-beta']);

        $adminA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'role' => 'admin',
        ]);

        $departmentB = Department::factory()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Secret Lab',
            'slug' => 'secret-lab',
        ]);

        $this->actingAs($adminA);

        Livewire::test(TeamManagement::class)
            ->call('openDepartmentQrModal', $departmentB->id)
            ->assertNotFound();
    }

    public function test_prefilled_department_qr_url_resolves_workspace_and_selects_matching_department(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'General Hospital',
            'slug' => 'general-hospital',
            'public_submission_enabled' => true,
        ]);

        Department::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'IT Helpdesk',
            'slug' => 'it-helpdesk',
        ]);

        $radiology = Department::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Radiology Unit',
            'slug' => 'radiology-unit',
        ]);

        $url = route('public.submit', [
            'workspace' => $tenant->slug,
            'facility' => $radiology->name,
        ]);

        $this->get($url)
            ->assertOk()
            ->assertSee('General Hospital');

        Livewire::withQueryParams([
            'workspace' => $tenant->slug,
            'facility' => $radiology->name,
        ])->test(PublicSubmitRequest::class)
            ->assertSet('tenantId', $tenant->id)
            ->assertSet('facility_name', 'Radiology Unit')
            ->assertSet('department_id', $radiology->id);
    }
}
