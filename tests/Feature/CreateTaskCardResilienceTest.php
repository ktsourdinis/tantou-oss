<?php

namespace Tests\Feature;

use App\Events\BoardUpdated;
use App\Jobs\DispatchPostItTelegramNotification;
use App\Livewire\CapacityBoard;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class CreateTaskCardResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_string_department_and_assignee_normalize_to_null_and_queue_telegram_job(): void
    {
        Queue::fake();
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create(['telegram_enabled' => true]);
        $admin = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'locale' => 'en',
        ]);

        $this->actingAs($admin);

        Livewire::test(CapacityBoard::class)
            ->call('openCreateModal', null, true)
            ->assertSeeHtml('wire:loading.attr="disabled"')
            ->assertSeeHtml('wire:target="createPostIt"')
            ->assertSeeHtml(':disabled="isUploading"')
            ->set('title', 'Fix mobile VPN timeout')
            ->set('departmentId', '')
            ->set('assigneeId', '')
            ->set('priority', 'high')
            ->set('weight', 2)
            ->call('createPostIt')
            ->assertHasNoErrors()
            ->assertSet('showCreateModal', false);

        $postIt = PostIt::where('title', 'Fix mobile VPN timeout')->first();
        $this->assertNotNull($postIt);
        $this->assertNull($postIt->department_id);
        $this->assertNull($postIt->assignee_id);
        $this->assertSame('backlog', $postIt->status);

        Queue::assertPushed(DispatchPostItTelegramNotification::class, function (DispatchPostItTelegramNotification $job) use ($postIt): bool {
            return $job->postItId === $postIt->id && $job->reason === 'created';
        });

        Event::assertDispatched(BoardUpdated::class);
    }

    public function test_validation_errors_on_assignee_department_and_description_are_visibly_rendered(): void
    {
        Queue::fake();
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();

        $otherDept = Department::factory()->create(['tenant_id' => $otherTenant->id]);

        $awayWorker = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'visibility' => 'public',
            'is_available' => false,
        ]);

        $client = User::factory()->client()->create([
            'tenant_id' => $tenant->id,
            'locale' => 'en',
        ]);

        $this->actingAs($client);

        // 1. Client selecting an away collaborator renders visible assigneeId error
        Livewire::test(CapacityBoard::class)
            ->call('openCreateModal')
            ->set('title', 'Urgent printer jam')
            ->set('assigneeId', $awayWorker->id)
            ->call('createPostIt')
            ->assertHasErrors(['assigneeId'])
            ->assertSet('showCreateModal', true)
            ->assertSee('This collaborator is currently away or not available for external requests.');

        // 2. Cross-tenant departmentId and >1000 char description fail validation visibly
        Livewire::test(CapacityBoard::class)
            ->call('openCreateModal')
            ->set('title', 'Invalid department and long description')
            ->set('departmentId', $otherDept->id)
            ->set('description', str_repeat('a', 1005))
            ->call('createPostIt')
            ->assertHasErrors(['departmentId', 'description'])
            ->assertSet('showCreateModal', true);
    }

    public function test_switching_department_clears_mismatched_assignee_and_assigns_correctly(): void
    {
        Queue::fake();
        Event::fake([BoardUpdated::class]);

        $tenant = Tenant::factory()->create();
        $deptA = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Dept A']);
        $deptB = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Dept B']);

        $workerA = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $deptA->id,
            'visibility' => 'public',
            'is_available' => true,
        ]);

        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($admin);

        Livewire::test(CapacityBoard::class)
            ->call('openCreateModal', $workerA->id)
            ->assertSet('assigneeId', $workerA->id)
            ->set('departmentId', $deptB->id)
            ->assertSet('assigneeId', null)
            ->set('title', 'Department B Backlog Card')
            ->call('createPostIt')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('post_its', [
            'tenant_id' => $tenant->id,
            'title' => 'Department B Backlog Card',
            'department_id' => $deptB->id,
            'assignee_id' => null,
            'status' => 'backlog',
        ]);
    }
}
