<?php

namespace Tests\Feature;

use App\Events\BoardUpdated;
use App\Livewire\CapacityBoard;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\PostItAttachment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class Step10AttachmentsAndPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Department $department;

    protected User $admin;

    protected User $coordinator;

    protected User $worker;

    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['name' => 'Acme Corporation']);
        $this->department = Department::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Engineering',
            'color' => '#6366f1',
        ]);

        $this->admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Admin Alex',
            'board_visibility' => 'private',
            'is_available' => true,
        ]);

        $this->coordinator = User::factory()->coordinator()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Coordinator Costas',
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        $this->worker = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Worker Walter',
            'board_visibility' => 'public',
            'is_available' => true,
        ]);

        $this->client = User::factory()->client()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Client Clara',
            'board_visibility' => 'none',
        ]);
    }

    public function test_post_it_attachment_model_helpers(): void
    {
        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->worker->id,
            'title' => 'Sample Task',
            'status' => 'backlog',
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => 20,
        ]);

        $img = PostItAttachment::create([
            'post_it_id' => $postIt->id,
            'uploader_id' => $this->worker->id,
            'original_name' => 'screenshot.png',
            'file_path' => 'tenants/1/post-its/1/test.png',
            'mime_type' => 'image/png',
            'file_size' => 2048,
        ]);

        $doc = PostItAttachment::create([
            'post_it_id' => $postIt->id,
            'uploader_id' => $this->worker->id,
            'original_name' => 'report.pdf',
            'file_path' => 'tenants/1/post-its/1/report.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2097152, // 2MB
        ]);

        $this->assertTrue($img->isImage());
        $this->assertFalse($doc->isImage());

        $this->assertEquals('2 KB', $img->formattedSize());
        $this->assertEquals('2 MB', $doc->formattedSize());
    }

    public function test_user_can_create_post_it_with_multiple_attachments_via_livewire(): void
    {
        Storage::fake('local');

        $image = UploadedFile::fake()->image('architecture.png', 100, 100);
        $pdf = UploadedFile::fake()->create('spec.pdf', 300, 'application/pdf');

        Livewire::actingAs($this->worker)
            ->test(CapacityBoard::class)
            ->set('title', 'Task with Attachments')
            ->set('description', 'Initial requirements with files')
            ->set('departmentId', $this->department->id)
            ->set('priority', 'high')
            ->set('weight', 3)
            ->set('newAttachments', [$image, $pdf])
            ->call('createPostIt');

        $postIt = PostIt::where('tenant_id', $this->tenant->id)->where('title', 'Task with Attachments')->first();
        $this->assertNotNull($postIt);

        $attachments = $postIt->attachments;
        $this->assertCount(2, $attachments);

        $filenames = $attachments->pluck('original_name')->all();
        $this->assertContains('architecture.png', $filenames);
        $this->assertContains('spec.pdf', $filenames);

        foreach ($attachments as $att) {
            $this->assertEquals($this->worker->id, $att->uploader_id);
            Storage::disk('local')->assertExists($att->file_path);
            $this->assertStringStartsWith("tenants/{$this->tenant->id}/post-its/{$postIt->id}/", $att->file_path);
        }
    }

    public function test_user_can_upload_and_delete_attachments_from_slide_over(): void
    {
        Storage::fake('local');

        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->client->id,
            'assignee_id' => $this->worker->id,
            'title' => 'Slide-Over Attachment Test',
            'status' => 'assigned',
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => 20,
        ]);

        $file = UploadedFile::fake()->create('notes.txt', 50, 'text/plain');

        // Worker assigned can upload in slide-over
        Livewire::actingAs($this->worker)
            ->test(CapacityBoard::class)
            ->call('openSlideOver', $postIt->id)
            ->set('slideOverNewAttachments', [$file])
            ->call('uploadSlideOverAttachments');

        $this->assertDatabaseHas('post_it_attachments', [
            'post_it_id' => $postIt->id,
            'uploader_id' => $this->worker->id,
            'original_name' => 'notes.txt',
        ]);

        $att = PostItAttachment::where('post_it_id', $postIt->id)->first();
        $this->assertNotNull($att);
        Storage::disk('local')->assertExists($att->file_path);

        // Delete attachment via Livewire
        Livewire::actingAs($this->worker)
            ->test(CapacityBoard::class)
            ->call('openSlideOver', $postIt->id)
            ->call('deleteAttachment', $att->id);

        $this->assertDatabaseMissing('post_it_attachments', [
            'id' => $att->id,
        ]);
        Storage::disk('local')->assertMissing($att->file_path);
    }

    public function test_slide_over_attachments_auto_save_via_lifecycle_hook(): void
    {
        Storage::fake('local');

        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->client->id,
            'assignee_id' => $this->worker->id,
            'title' => 'Auto-Save Hook Test',
            'status' => 'assigned',
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => 20,
        ]);

        $file = UploadedFile::fake()->create('auto_saved.pdf', 80, 'application/pdf');

        // Setting slideOverNewAttachments triggers updatedSlideOverNewAttachments() hook
        Livewire::actingAs($this->worker)
            ->test(CapacityBoard::class)
            ->call('openSlideOver', $postIt->id)
            ->set('slideOverNewAttachments', [$file]);

        $this->assertDatabaseHas('post_it_attachments', [
            'post_it_id' => $postIt->id,
            'uploader_id' => $this->worker->id,
            'original_name' => 'auto_saved.pdf',
        ]);
    }

    public function test_attachment_download_and_preview_route_authorization(): void
    {
        Storage::fake('local');

        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->worker->id,
            'assignee_id' => $this->coordinator->id,
            'title' => 'Internal Task with Image',
            'status' => 'doing',
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => 20,
        ]);

        $uploaded = UploadedFile::fake()->image('diagram.png', 50, 50);
        $path = $uploaded->store("tenants/{$this->tenant->id}/post-its/{$postIt->id}", 'local');

        $attachment = PostItAttachment::create([
            'post_it_id' => $postIt->id,
            'uploader_id' => $this->worker->id,
            'original_name' => 'diagram.png',
            'file_path' => $path,
            'mime_type' => 'image/png',
            'file_size' => 1024,
        ]);

        // 1. Authorized user (worker who created it) can view inline preview
        $response = $this->actingAs($this->worker)->get(route('attachments.show', $attachment));
        $response->assertStatus(200);
        $this->assertStringContainsString('image/png', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));

        // 2. Download parameter forces attachment disposition
        $downloadResponse = $this->actingAs($this->worker)->get(route('attachments.show', ['attachment' => $attachment, 'download' => 1]));
        $downloadResponse->assertStatus(200);
        $this->assertStringContainsString('attachment', $downloadResponse->headers->get('Content-Disposition'));

        // 3. Admin can view attachment
        $this->actingAs($this->admin)->get(route('attachments.show', $attachment))->assertStatus(200);

        // 4. Client trying to access attachment of other users' active card is blocked (403)
        $this->actingAs($this->client)->get(route('attachments.show', $attachment))->assertStatus(403);

        // 5. User from another tenant is blocked (403)
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->worker()->create(['tenant_id' => $otherTenant->id]);
        $this->actingAs($otherUser)->get(route('attachments.show', $attachment))->assertStatus(403);
    }

    public function test_card_renders_attachments_count_badge(): void
    {
        Storage::fake('local');

        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->worker->id,
            'title' => 'Badge Test Card',
            'status' => 'backlog',
            'priority' => 'normal',
            'weight' => 1,
            'bounty_points' => 10,
        ]);

        PostItAttachment::create([
            'post_it_id' => $postIt->id,
            'uploader_id' => $this->worker->id,
            'original_name' => 'doc1.pdf',
            'file_path' => 'dummy/path1.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);

        PostItAttachment::create([
            'post_it_id' => $postIt->id,
            'uploader_id' => $this->worker->id,
            'original_name' => 'doc2.pdf',
            'file_path' => 'dummy/path2.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
        ]);

        Livewire::actingAs($this->worker)
            ->test(CapacityBoard::class)
            ->assertSee('Badge Test Card')
            ->assertSeeHtml('title="2 '.__('attachments').'"');
    }

    public function test_board_updated_event_broadcasts_actor_user_id(): void
    {
        $this->actingAs($this->worker);

        $event = new BoardUpdated($this->tenant->id);

        $this->assertEquals($this->worker->id, $event->actorUserId);
        $payload = $event->broadcastWith();

        $this->assertEquals($this->tenant->id, $payload['tenantId']);
        $this->assertEquals($this->worker->id, $payload['actorUserId']);
    }
}
