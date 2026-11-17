<?php

namespace Tests\Feature;

use App\Livewire\CapacityBoard;
use App\Livewire\PublicTrackRequest;
use App\Livewire\TeamManagement;
use App\Mail\PostItCommentNotificationMail;
use App\Mail\PostItStatusChangedMail;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class CommentAndResolutionNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Department $department;

    protected User $admin;

    protected User $worker;

    protected User $requesterUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'name' => 'Acme Operations',
            'slug' => 'acme-ops',
            'telegram_enabled' => true,
            'telegram_bot_token' => 'mock-token-step41',
            'telegram_privacy_mode' => false,
        ]);

        $this->department = Department::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facilities & IT',
            'slug' => 'facilities-it',
            'color' => '#6366f1',
        ]);

        $this->admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Admin Kostas',
            'email' => 'admin@acme.gr',
        ]);

        $this->worker = User::factory()->worker()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Tech Nikos',
            'email' => 'nikos@acme.gr',
            'telegram_chat_id' => '777111222',
            'telegram_notifications_enabled' => true,
            'is_available' => true,
        ]);

        $this->requesterUser = User::factory()->client()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => null,
            'name' => 'Client Maria',
            'email' => 'maria@client.gr',
            'telegram_chat_id' => '888333444',
            'telegram_notifications_enabled' => true,
        ]);
    }

    /**
     * 1. Creating a new member in TeamManagement immediately renders their full name,
     * email, and role in the roster table without blank rows.
     */
    public function test_creating_new_member_in_team_management_renders_immediately(): void
    {
        Livewire::actingAs($this->admin)
            ->test(TeamManagement::class)
            ->call('setActiveTab', 'roster')
            ->call('openCreateUserModal')
            ->set('userName', 'Ioanna Engineer')
            ->set('userEmail', 'ioanna@acme.gr')
            ->set('userRole', 'worker')
            ->set('userDepartmentId', $this->department->id)
            ->set('userPassword', 'Passw0rdSecure!#')
            ->set('userMaxCapacity', 15)
            ->call('saveUser')
            ->assertHasNoErrors()
            ->assertSet('showUserModal', false)
            ->assertSee('Ioanna Engineer')
            ->assertSee('ioanna@acme.gr')
            ->assertSee('worker')
            ->assertSee('Facilities & IT');

        $this->assertDatabaseHas('users', [
            'email' => 'ioanna@acme.gr',
            'name' => 'Ioanna Engineer',
            'max_capacity' => 15,
        ]);
    }

    /**
     * 2. Direct task assignment notifies ONLY the assigned worker and NEVER spams the department channel.
     */
    public function test_direct_task_assignment_notifies_only_assigned_worker_and_not_department(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 991]], 200),
        ]);

        // Department has a linked Telegram Group chat
        $this->department->update([
            'telegram_chat_id' => '-10044556677',
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->requesterUser->id,
            'assignee_id' => $this->worker->id,
            'title' => 'Server Maintenance',
            'status' => 'assigned',
            'priority' => 'high',
            'weight' => 3,
            'bounty_points' => 300,
        ]);

        app(TelegramBotService::class)->routePostItNotification($postIt);

        // Verify Telegram API called for Worker chat ID
        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['chat_id']) && (string) $data['chat_id'] === '777111222';
        });

        // Verify Telegram API NEVER called for Department Group chat ID
        Http::assertNotSent(function ($request) {
            $data = $request->data();

            return isset($data['chat_id']) && (string) $data['chat_id'] === '-10044556677';
        });
    }

    /**
     * 3A. Posting a comment by a Worker notifies the Requester via Telegram when linked.
     */
    public function test_posting_comment_by_worker_notifies_requester_via_telegram(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1002]], 200),
        ]);
        Mail::fake();

        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->requesterUser->id,
            'assignee_id' => $this->worker->id,
            'title' => 'HVAC Filter Replacement',
            'status' => 'doing',
            'priority' => 'normal',
            'weight' => 2,
        ]);

        $comment = $postIt->comments()->create([
            'user_id' => $this->worker->id,
            'body' => 'I have arrived on site and inspected the filters.',
        ]);

        app(TelegramBotService::class)->notifyCommentAdded($postIt, $comment, $this->worker);

        // Assert Telegram message sent to Requester's chat_id
        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['chat_id'])
                && (string) $data['chat_id'] === '888333444'
                && str_contains($data['text'], 'Tech Nikos')
                && str_contains($data['text'], 'I have arrived on site');
        });

        // Email fallback should NOT be sent because Telegram succeeded
        Mail::assertNothingSent();
    }

    /**
     * 3B. Posting a comment by a Worker notifies the Requester via Email fallback when Telegram is not linked.
     */
    public function test_posting_comment_by_worker_falls_back_to_email_for_requester(): void
    {
        Mail::fake();
        Http::fake();

        // Requester has no telegram
        $this->requesterUser->update([
            'telegram_chat_id' => null,
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->requesterUser->id,
            'assignee_id' => $this->worker->id,
            'title' => 'Projector Bulb Issue',
            'status' => 'doing',
            'priority' => 'normal',
            'weight' => 2,
        ]);

        $comment = $postIt->comments()->create([
            'user_id' => $this->worker->id,
            'body' => 'Replacement bulb has been ordered and will arrive tomorrow.',
        ]);

        app(TelegramBotService::class)->notifyCommentAdded($postIt, $comment, $this->worker);

        Mail::assertSent(PostItCommentNotificationMail::class, function ($mail) {
            return $mail->hasTo('maria@client.gr')
                && $mail->postIt->title === 'Projector Bulb Issue'
                && str_contains($mail->comment->body, 'Replacement bulb has been ordered');
        });

        Http::assertNothingSent();
    }

    /**
     * 3C. Guest on public track page replies, notifying assigned worker via Telegram.
     */
    public function test_guest_comment_on_public_tracker_notifies_assigned_worker_via_telegram(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1003]], 200),
        ]);
        Mail::fake();

        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => null,
            'guest_name' => 'Elena Visitor',
            'guest_contact' => 'elena@external.gr',
            'requester_email' => 'elena@external.gr',
            'assignee_id' => $this->worker->id,
            'title' => 'Water Leak in Floor 2 Restroom',
            'status' => 'doing',
            'priority' => 'urgent',
            'weight' => 4,
        ]);

        Livewire::test(PublicTrackRequest::class, ['token' => $postIt->tracking_token])
            ->set('newCommentBody', 'Water is starting to overflow under the door!')
            ->call('addComment')
            ->assertHasNoErrors()
            ->assertSee('Water is starting to overflow under the door!');

        // Assert Telegram notification sent to assigned worker's chat
        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['chat_id'])
                && (string) $data['chat_id'] === '777111222'
                && str_contains($data['text'], 'Water is starting to overflow');
        });

        Mail::assertNothingSent();
    }

    /**
     * 3D. Guest comment notifies assigned worker via Email fallback if worker has no Telegram.
     */
    public function test_guest_comment_falls_back_to_email_for_worker_without_telegram(): void
    {
        Mail::fake();
        Http::fake();

        $this->worker->update([
            'telegram_chat_id' => null,
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => null,
            'guest_name' => 'George Visitor',
            'guest_contact' => 'george@visitor.com',
            'requester_email' => 'george@visitor.com',
            'assignee_id' => $this->worker->id,
            'title' => 'Elevator B Squeaking',
            'status' => 'doing',
            'priority' => 'normal',
            'weight' => 2,
        ]);

        $comment = $postIt->comments()->create([
            'user_id' => null,
            'guest_name' => 'George Visitor',
            'body' => 'Noticeable noise between floors 3 and 4.',
        ]);

        app(TelegramBotService::class)->notifyCommentAdded($postIt, $comment, null);

        Mail::assertSent(PostItCommentNotificationMail::class, function ($mail) {
            return $mail->hasTo('nikos@acme.gr')
                && $mail->postIt->title === 'Elevator B Squeaking';
        });

        Http::assertNothingSent();
    }

    /**
     * 4A. Completing a task notifies requester via Telegram when linked.
     */
    public function test_completing_task_notifies_requester_via_telegram(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1004]], 200),
        ]);
        Mail::fake();

        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->requesterUser->id,
            'assignee_id' => $this->worker->id,
            'title' => 'Fix AC in Conference Room A',
            'status' => 'doing',
            'priority' => 'high',
            'weight' => 3,
            'bounty_points' => 300,
            'started_at' => now()->subHour(),
        ]);

        Livewire::actingAs($this->worker)
            ->test(CapacityBoard::class)
            ->call('dropIntoCompleted', $postIt->id);

        $this->assertEquals('done', $postIt->fresh()->status);

        Http::assertSent(function ($request) use ($postIt) {
            $data = $request->data();

            return isset($data['chat_id'])
                && (string) $data['chat_id'] === '888333444'
                && str_contains($data['text'], 'completed')
                && str_contains($data['text'], $postIt->tracking_token);
        });

        Mail::assertNothingSent();
    }

    /**
     * 4B. Completing a task falls back to sending PostItStatusChangedMail to requester's email.
     */
    public function test_completing_task_falls_back_to_email_for_requester(): void
    {
        Mail::fake();
        Http::fake();

        $this->requesterUser->update([
            'telegram_chat_id' => null,
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->requesterUser->id,
            'assignee_id' => $this->worker->id,
            'title' => 'Network Switch Reboot',
            'status' => 'doing',
            'priority' => 'normal',
            'weight' => 2,
            'bounty_points' => 200,
            'started_at' => now()->subMinutes(30),
        ]);

        Livewire::actingAs($this->worker)
            ->test(CapacityBoard::class)
            ->call('dropIntoCompleted', $postIt->id);

        $this->assertEquals('done', $postIt->fresh()->status);

        Mail::assertSent(PostItStatusChangedMail::class, function ($mail) {
            return $mail->hasTo('maria@client.gr')
                && $mail->resolutionType === 'done'
                && $mail->postIt->title === 'Network Switch Reboot';
        });

        Http::assertNothingSent();
    }

    /**
     * 4C. Completing guest task sends PostItStatusChangedMail to requester_email.
     */
    public function test_completing_guest_task_sends_email_to_guest_requester_email(): void
    {
        Mail::fake();
        Http::fake();

        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => null,
            'guest_name' => 'Guest Petros',
            'guest_contact' => 'petros@guestcorp.gr',
            'requester_email' => 'petros@guestcorp.gr',
            'assignee_id' => $this->worker->id,
            'title' => 'Coffee Machine Jammed',
            'status' => 'doing',
            'priority' => 'low',
            'weight' => 1,
            'bounty_points' => 100,
            'started_at' => now()->subMinutes(15),
        ]);

        Livewire::actingAs($this->worker)
            ->test(CapacityBoard::class)
            ->call('dropIntoCompleted', $postIt->id);

        $this->assertEquals('done', $postIt->fresh()->status);

        Mail::assertSent(PostItStatusChangedMail::class, function ($mail) {
            return $mail->hasTo('petros@guestcorp.gr')
                && $mail->resolutionType === 'done'
                && $mail->postIt->title === 'Coffee Machine Jammed';
        });
    }

    /**
     * 4D. Canceling an unstarted task notifies requester with canceled status.
     */
    public function test_canceling_unstarted_task_notifies_requester_with_canceled_status(): void
    {
        Mail::fake();
        Http::fake();

        $this->requesterUser->update([
            'telegram_chat_id' => null,
        ]);

        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->requesterUser->id,
            'assignee_id' => $this->worker->id,
            'title' => 'Unneeded Extra Chair Request',
            'status' => 'assigned',
            'priority' => 'low',
            'weight' => 1,
            'started_at' => null,
        ]);

        Livewire::actingAs($this->worker)
            ->test(CapacityBoard::class)
            ->call('dropIntoCompleted', $postIt->id);

        $fresh = $postIt->fresh();
        $this->assertEquals('done', $fresh->status);
        $this->assertTrue($fresh->is_canceled);

        Mail::assertSent(PostItStatusChangedMail::class, function ($mail) {
            return $mail->hasTo('maria@client.gr')
                && $mail->resolutionType === 'canceled'
                && $mail->postIt->title === 'Unneeded Extra Chair Request';
        });
    }

    /**
     * 4E. Self-closing by the requester themselves does NOT send redundant notifications.
     */
    public function test_self_closing_by_requester_does_not_send_notification(): void
    {
        Mail::fake();
        Http::fake();

        $postIt = PostIt::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->requesterUser->id,
            'assignee_id' => $this->worker->id,
            'title' => 'Issue Resolved on My Own',
            'status' => 'assigned',
            'priority' => 'normal',
            'weight' => 2,
        ]);

        Livewire::actingAs($this->requesterUser)
            ->test(CapacityBoard::class)
            ->call('closeByRequester', $postIt->id, 'Resolved myself');

        $this->assertEquals('done', $postIt->fresh()->status);

        // Neither email nor telegram should be sent for self-close
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }
}
