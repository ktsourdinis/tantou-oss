<?php

namespace Tests\Feature;

use App\Livewire\CapacityBoard;
use App\Livewire\TeamManagement;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Step11InternationalizationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Department $department;

    protected User $admin;

    protected User $worker;

    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Hellenic Ops Demo',
            'slug' => 'hellenic-ops',
        ]);

        $this->department = Department::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Engineering',
            'slug' => 'engineering',
            'color' => '#6366f1',
        ]);

        $this->admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Admin User',
            'locale' => 'en',
        ]);

        $this->worker = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Worker User',
            'locale' => 'en',
        ]);

        $this->client = User::factory()->client()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Client User',
            'locale' => 'en',
        ]);
    }

    public function test_default_application_locale_is_english(): void
    {
        $this->assertEquals('en', config('app.locale'));
        $this->assertEquals('en', config('app.fallback_locale'));
    }

    public function test_guest_can_switch_locale_via_post_route(): void
    {
        $response = $this->post(route('locale.switch', ['locale' => 'el']));

        $response->assertRedirect();
        $response->assertSessionHas('locale', 'el');
    }

    public function test_authenticated_user_switch_locale_persists_in_database_and_session(): void
    {
        $this->actingAs($this->worker);

        $response = $this->post(route('locale.switch', ['locale' => 'el']));

        $response->assertRedirect();
        $response->assertSessionHas('locale', 'el');
        $this->assertEquals('el', $this->worker->fresh()->locale);
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $response = $this->post(route('locale.switch', ['locale' => 'fr']));

        $response->assertStatus(400);
    }

    public function test_middleware_sets_app_locale_from_authenticated_user(): void
    {
        $this->worker->update(['locale' => 'el']);

        $this->actingAs($this->worker)
            ->get('/board')
            ->assertOk();

        $this->assertEquals('el', app()->getLocale());
    }

    public function test_middleware_sets_app_locale_from_session_when_user_locale_is_null_or_guest(): void
    {
        $this->withSession(['locale' => 'el'])
            ->get('/')
            ->assertOk();

        $this->assertEquals('el', app()->getLocale());
    }

    public function test_capacity_board_renders_english_by_default(): void
    {
        Livewire::actingAs($this->worker)
            ->test(CapacityBoard::class)
            ->assertSee('Capacity Board')
            ->assertSee('New Post-it')
            ->assertSee('Backlog');
    }

    public function test_capacity_board_renders_greek_when_greek_locale_active(): void
    {
        $this->worker->update(['locale' => 'el']);
        app()->setLocale('el');

        Livewire::actingAs($this->worker)
            ->test(CapacityBoard::class)
            ->assertSee('Πίνακας Δυναμικότητας')
            ->assertSee('Νέο Post-it')
            ->assertSee('Εκκρεμή');
    }

    public function test_livewire_switch_locale_action_updates_locale_and_redirects(): void
    {
        Livewire::actingAs($this->worker)
            ->test(CapacityBoard::class)
            ->assertSee('Capacity Board')
            ->call('switchLocale', 'el')
            ->assertRedirect();

        $this->assertEquals('el', $this->worker->fresh()->locale);
        $this->assertEquals('el', session('locale'));
    }

    public function test_team_management_renders_greek_translations(): void
    {
        $this->admin->update(['locale' => 'el']);
        app()->setLocale('el');

        Livewire::actingAs($this->admin)
            ->test(TeamManagement::class)
            ->assertSee('Ομάδα & Κατάταξη')
            ->assertSee('Τμήματα')
            ->assertSee('Μέλη Ομάδας');
    }

    public function test_anonymized_requester_translates_between_locales(): void
    {
        $otherClient = User::factory()->client()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Secret Client',
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $otherClient->id,
            'assignee_id' => $this->worker->id,
            'status' => 'assigned',
            'title' => 'Confidential Task',
            'weight' => 2,
            'priority' => 'normal',
        ]);

        // English: another client sees "Anonymous Request"
        app()->setLocale('en');
        Livewire::actingAs($this->client)
            ->test(CapacityBoard::class)
            ->assertSee('Anonymous Request')
            ->assertDontSee('Secret Client');

        // Greek: another client sees "Ανώνυμο Αίτημα"
        app()->setLocale('el');
        Livewire::actingAs($this->client)
            ->test(CapacityBoard::class)
            ->assertSee('Ανώνυμο Αίτημα')
            ->assertDontSee('Secret Client');
    }
}
