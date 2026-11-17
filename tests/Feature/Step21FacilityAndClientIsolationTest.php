<?php

namespace Tests\Feature;

use App\Livewire\CapacityBoard;
use App\Livewire\TeamManagement;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Step21FacilityAndClientIsolationTest extends TestCase
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

    public function test_facility_name_is_saved_on_post_it_creation_from_requester_facility(): void
    {
        $tenant = Tenant::factory()->create();
        $client = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'client',
            'facility_name' => 'Ακτινολογικό',
        ]);

        $this->actingAs($client);

        Livewire::test(CapacityBoard::class)
            ->set('title', 'CT Scan Analysis Task')
            ->set('description', 'Urgent CT scan analysis for patient.')
            ->set('priority', 'urgent')
            ->set('weight', 3)
            ->call('createPostIt')
            ->assertHasNoErrors();

        $postIt = PostIt::where('tenant_id', $tenant->id)->where('title', 'CT Scan Analysis Task')->first();
        $this->assertNotNull($postIt);
        $this->assertSame('Ακτινολογικό', $postIt->facility_name);
        $this->assertSame($client->id, $postIt->requester_id);
    }

    public function test_strict_client_isolation_hides_foreign_post_it_title_and_blocks_inspection(): void
    {
        $tenant = Tenant::factory()->create([
            'strict_client_isolation' => true,
        ]);

        $worker = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'worker',
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        // Client A from Facility A
        $clientA = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'client',
            'facility_name' => 'Facility A',
        ]);

        // Client B from Facility B
        $clientB = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'client',
            'facility_name' => 'Facility B',
        ]);

        // Colleague of Client A from same Facility A
        $clientA2 = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'client',
            'facility_name' => 'Facility A',
        ]);

        // Post-it originated from Facility A
        $taskA = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $clientA->id,
            'facility_name' => 'Facility A',
            'assignee_id' => $worker->id,
            'title' => 'Top Secret Facility A Task',
            'status' => 'assigned',
        ]);

        // Post-it originated from Facility B
        $taskB = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $clientB->id,
            'facility_name' => 'Facility B',
            'assignee_id' => $worker->id,
            'title' => 'Confidential Facility B Mission',
            'status' => 'assigned',
        ]);

        // Backlog tasks
        $backlogA = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $clientA->id,
            'facility_name' => 'Facility A',
            'title' => 'Facility A Backlog',
            'status' => 'backlog',
            'assignee_id' => null,
        ]);

        $backlogB = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $clientB->id,
            'facility_name' => 'Facility B',
            'title' => 'Facility B Backlog',
            'status' => 'backlog',
            'assignee_id' => null,
        ]);

        // Done tasks
        $doneA = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $clientA->id,
            'facility_name' => 'Facility A',
            'title' => 'Facility A Done Task',
            'status' => 'done',
            'completed_at' => now(),
        ]);

        $doneB = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $clientB->id,
            'facility_name' => 'Facility B',
            'title' => 'Facility B Done Task',
            'status' => 'done',
            'completed_at' => now(),
        ]);

        // 1. Client A2 (same Facility A) views the board
        $this->actingAs($clientA2);

        $test = Livewire::test(CapacityBoard::class);

        // Client A2 should see task A's real title and unmasked details in collaborator column
        $test->assertViewHas('workers', function ($workers) use ($worker, $taskA, $taskB) {
            $collab = $workers->firstWhere('id', $worker->id);
            $cardA = $collab?->assignedPostIts->firstWhere('id', $taskA->id);
            $cardB = $collab?->assignedPostIts->firstWhere('id', $taskB->id);

            // Same facility card is visible
            $sameFacilityOk = $cardA && $cardA->title === 'Top Secret Facility A Task' && ! ($cardA->is_foreign_slot ?? false);

            // Foreign facility card B is anonymized into occupied slot
            $foreignSlotOk = $cardB && ($cardB->is_foreign_slot ?? false) && $cardB->title === __('Occupied Slot');

            return $sameFacilityOk && $foreignSlotOk;
        });

        // Client A2 should only see Facility A's backlog, NOT Facility B's backlog
        $test->assertViewHas('backlogPostIts', function ($backlog) use ($backlogA, $backlogB) {
            return $backlog->contains('id', $backlogA->id) && ! $backlog->contains('id', $backlogB->id);
        });

        // Client A2 should only see Facility A's completed tasks, NOT Facility B's completed tasks
        $test->assertViewHas('donePostIts', function ($done) use ($doneA, $doneB) {
            return $done->contains('id', $doneA->id) && ! $done->contains('id', $doneB->id);
        });

        // Client A2 can inspect Task A (same facility)
        $test->call('openSlideOver', $taskA->id)
            ->assertSet('selectedPostItId', $taskA->id);

        // Client A2 CANNOT inspect Task B (foreign facility)
        $test->call('closeSlideOver')
            ->assertSet('selectedPostItId', null)
            ->call('openSlideOver', $taskB->id)
            ->assertSet('selectedPostItId', null);
    }

    public function test_admin_can_manage_facility_name_in_team_roster(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
        $client = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'client',
            'facility_name' => null,
        ]);

        $this->actingAs($admin);

        Livewire::test(TeamManagement::class)
            ->call('openEditUserModal', $client->id)
            ->assertSet('userFacilityName', '')
            ->set('userFacilityName', 'Cardiology Clinic')
            ->call('saveUser')
            ->assertHasNoErrors();

        $client->refresh();
        $this->assertSame('Cardiology Clinic', $client->facility_name);
    }

    public function test_admin_can_toggle_strict_client_isolation_in_workspace_settings(): void
    {
        $tenant = Tenant::factory()->create([
            'strict_client_isolation' => false,
        ]);
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($admin);

        Livewire::test(CapacityBoard::class)
            ->call('openWorkspaceSettingsModal')
            ->assertSet('strictClientIsolation', false)
            ->set('strictClientIsolation', true)
            ->call('saveWorkspaceSettings')
            ->assertHasNoErrors();

        $tenant->refresh();
        $this->assertTrue($tenant->strict_client_isolation);
    }

    public function test_requests_history_modal_scoping_and_search(): void
    {
        $tenant = Tenant::factory()->create();
        $clientA = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'client',
            'facility_name' => 'Lab Alpha',
        ]);
        $clientB = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'client',
            'facility_name' => 'Lab Beta',
        ]);

        $postIt1 = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $clientA->id,
            'facility_name' => 'Lab Alpha',
            'title' => 'Blood Test Specimen 101',
            'status' => 'backlog',
        ]);

        $postIt2 = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $clientA->id,
            'facility_name' => 'Lab Alpha',
            'title' => 'DNA Sequencing Batch',
            'status' => 'done',
            'completed_at' => now(),
        ]);

        $postItForeign = PostIt::factory()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $clientB->id,
            'facility_name' => 'Lab Beta',
            'title' => 'X-Ray Pelvis Scan',
            'status' => 'backlog',
        ]);

        $this->actingAs($clientA);

        $component = Livewire::test(CapacityBoard::class)
            ->call('openRequestsHistoryModal')
            ->assertSet('showRequestsHistoryModal', true);

        // Requests for Client A should include postIt1 and postIt2, but NOT postItForeign
        $history = $component->instance()->historyRequests();
        $this->assertTrue($history->contains('id', $postIt1->id));
        $this->assertTrue($history->contains('id', $postIt2->id));
        $this->assertFalse($history->contains('id', $postItForeign->id));

        // Test filter by completed
        $component->set('historyStatusFilter', 'completed');
        $historyCompleted = $component->instance()->historyRequests();
        $this->assertFalse($historyCompleted->contains('id', $postIt1->id));
        $this->assertTrue($historyCompleted->contains('id', $postIt2->id));

        // Test search filter
        $component->set('historyStatusFilter', 'all')
            ->set('historySearch', 'Specimen');
        $historySearch = $component->instance()->historyRequests();
        $this->assertTrue($historySearch->contains('id', $postIt1->id));
        $this->assertFalse($historySearch->contains('id', $postIt2->id));

        // Test opening detail from history
        $component->call('openDetailFromHistory', $postIt1->id)
            ->assertSet('showRequestsHistoryModal', false)
            ->assertSet('selectedPostItId', $postIt1->id);
    }
}
