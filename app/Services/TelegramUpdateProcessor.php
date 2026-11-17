<?php

namespace App\Services;

use App\Events\BoardUpdated;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\PostItTransfer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Edition;

class TelegramUpdateProcessor
{
    public function __construct(
        protected TelegramBotService $bot
    ) {}

    /**
     * Process an incoming Telegram update from webhook or polling transport.
     *
     * @param  array<string, mixed>  $update
     */
    public function processUpdate(array $update, ?Tenant $botTenant = null): bool
    {
        // 1. Handle Callback Query (Inline Keyboard Buttons)
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query'], $botTenant);

            return true;
        }

        // 2. Handle Text Messages
        if (isset($update['message'])) {
            $this->handleMessage($update['message'], $botTenant);

            return true;
        }

        return false;
    }

    /**
     * Handle incoming callback query.
     *
     * @param  array<string, mixed>  $callback
     */
    protected function handleCallbackQuery(array $callback, ?Tenant $botTenant = null): void
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $fromId = (string) ($callback['from']['id'] ?? $callback['message']['chat']['id'] ?? '');
        $data = (string) ($callback['data'] ?? '');
        $message = $callback['message'] ?? null;
        $chatId = isset($message['chat']['id']) ? (string) $message['chat']['id'] : $fromId;
        $messageId = isset($message['message_id']) ? (int) $message['message_id'] : null;

        $parts = explode(':', $data);
        $action = $parts[0] ?? '';
        $tenantId = 0;
        $targetId = 0;

        if (count($parts) >= 3) {
            $tenantId = (int) $parts[1];
            $targetId = (int) $parts[2];
        } elseif (count($parts) === 2) {
            $targetId = (int) $parts[1];
            if ($botTenant) {
                $tenantId = $botTenant->id;
            } else {
                $foundPostIt = PostIt::withoutGlobalScopes()->find($targetId);
                if ($foundPostIt) {
                    $tenantId = (int) $foundPostIt->tenant_id;
                }
            }
        }

        // Resolve Tenant
        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            if ($botTenant && $botTenant->id === $tenantId) {
                $tenant = $botTenant;
            } else {
                return;
            }
        }

        // Bind tenant to container so currentTenant is available everywhere
        app()->instance('currentTenant', $tenant);

        // If bot is scoped to a specific tenant, verify tenant matches
        if ($botTenant && $botTenant->id !== $tenant->id) {
            $this->bot->answerCallbackQuery($botTenant, $callbackId, '⛔ Cross-workspace action blocked.', true);

            return;
        }

        // STRICT MULTI-TENANT ISOLATION CHECK:
        // User must exist in the specified tenant with matching telegram_chat_id
        $user = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('telegram_chat_id', $fromId)
            ->first();

        if (! $user) {
            $this->bot->answerCallbackQuery($tenant, $callbackId, '⛔ Unauthorized or unlinked account in this workspace.', true);

            return;
        }

        switch ($action) {
            case 'claim':
                $this->handleClaimAction($tenant, $user, $callbackId, $targetId, $chatId, $messageId);
                break;

            case 'start':
                $this->handleStartAction($tenant, $user, $callbackId, $targetId, $chatId, $messageId);
                break;

            case 'done':
                $this->handleDoneAction($tenant, $user, $callbackId, $targetId, $chatId, $messageId);
                break;

            case 'avail':
                $this->handleAvailabilityToggle($tenant, $user, $callbackId, $targetId, $chatId, $messageId);
                break;

            case 'vis':
                $this->handleVisibilityChange($tenant, $user, $callbackId, $targetId, (string) ($parts[3] ?? 'public'), $chatId, $messageId);
                break;

            default:
                $this->bot->answerCallbackQuery($tenant, $callbackId, 'Unknown action.');
                break;
        }
    }

    /**
     * Handle claim callback action.
     */
    protected function handleClaimAction(Tenant $tenant, User $user, string $callbackId, int $postItId, string $chatId, ?int $messageId): void
    {
        // STRICT MULTI-TENANT CHECK: PostIt must belong to the exact tenant
        $postIt = PostIt::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('id', $postItId)->first();
        if (! $postIt) {
            $this->bot->answerCallbackQuery($tenant, $callbackId, '⛔ Request not found in this workspace.', true);

            return;
        }

        if ($postIt->status !== 'backlog' || $postIt->assignee_id !== null) {
            $this->bot->answerCallbackQuery($tenant, $callbackId, '⚠️ Already claimed or in progress.', true);

            return;
        }

        // Check user capacity
        $newCap = $user->current_capacity + $postIt->weight;
        if ($newCap > $user->max_capacity) {
            $this->bot->answerCallbackQuery($tenant, $callbackId, "⚠️ Over capacity! ({$user->current_capacity}/{$user->max_capacity} cap)", true);

            return;
        }

        // Claim task
        $postIt->assignee_id = $user->id;
        $postIt->assigned_by_id = $user->id;
        $postIt->status = 'assigned';
        $postIt->save();

        $user->update([
            'current_capacity' => (int) PostIt::withoutGlobalScopes()
                ->where('assignee_id', $user->id)
                ->whereIn('status', ['assigned', 'doing'])
                ->sum('weight'),
        ]);

        PostItTransfer::create([
            'post_it_id' => $postIt->id,
            'actor_id' => $user->id,
            'from_user_id' => null,
            'to_user_id' => $user->id,
            'action' => 'assigned',
        ]);

        BoardUpdated::dispatch($tenant->id, $user->id);

        $this->bot->answerCallbackQuery($tenant, $callbackId, "🙋‍♂️ Claimed #{$postIt->tracking_token}!");

        if ($messageId) {
            $boardUrl = Edition::workspaceUrl($tenant, '/board');
            $newKeyboard = [
                [
                    ['text' => '▶️ Start Working', 'callback_data' => "start:{$tenant->id}:{$postIt->id}"],
                    ['text' => '✅ Complete', 'callback_data' => "done:{$tenant->id}:{$postIt->id}"],
                    ['text' => '🔗 Open Board', 'url' => $boardUrl],
                ],
            ];
            $this->bot->editMessageReplyMarkup($tenant, $chatId, $messageId, $newKeyboard);
        }
    }

    /**
     * Handle start callback action.
     */
    protected function handleStartAction(Tenant $tenant, User $user, string $callbackId, int $postItId, string $chatId, ?int $messageId): void
    {
        $postIt = PostIt::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('id', $postItId)->first();
        if (! $postIt) {
            $this->bot->answerCallbackQuery($tenant, $callbackId, '⛔ Request not found.', true);

            return;
        }

        // Verify authorization: must be assignee, coordinator in department, admin, or unassigned card
        $canStart = $user->role === 'admin'
            || (int) $postIt->assignee_id === (int) $user->id
            || ($postIt->assignee_id === null)
            || ($user->role === 'coordinator' && (int) $postIt->department_id === (int) $user->department_id);

        if (! $canStart) {
            $this->bot->answerCallbackQuery($tenant, $callbackId, '⛔ Unauthorized to start this request.', true);

            return;
        }

        $fromAssigneeId = $postIt->assignee_id;
        $postIt->assignee_id = $postIt->assignee_id ?: $user->id;
        $postIt->status = 'doing';
        $postIt->started_at = $postIt->started_at ?: now();
        $postIt->save();

        // Update assignee capacity
        $assignee = User::withoutGlobalScopes()->find($postIt->assignee_id);
        if ($assignee) {
            $assignee->update([
                'current_capacity' => (int) PostIt::withoutGlobalScopes()
                    ->where('assignee_id', $assignee->id)
                    ->whereIn('status', ['assigned', 'doing'])
                    ->sum('weight'),
            ]);
        }

        PostItTransfer::create([
            'post_it_id' => $postIt->id,
            'actor_id' => $user->id,
            'from_user_id' => $fromAssigneeId,
            'to_user_id' => $postIt->assignee_id,
            'action' => 'started',
        ]);

        BoardUpdated::dispatch($tenant->id, $user->id);

        $this->bot->answerCallbackQuery($tenant, $callbackId, "▶️ Started working on #{$postIt->tracking_token}!");

        if ($messageId) {
            $boardUrl = Edition::workspaceUrl($tenant, '/board');
            $newKeyboard = [
                [
                    ['text' => '✅ Complete', 'callback_data' => "done:{$tenant->id}:{$postIt->id}"],
                    ['text' => '🔗 Open Board', 'url' => $boardUrl],
                ],
            ];
            $this->bot->editMessageReplyMarkup($tenant, $chatId, $messageId, $newKeyboard);
        }
    }

    /**
     * Handle done callback action.
     */
    protected function handleDoneAction(Tenant $tenant, User $user, string $callbackId, int $postItId, string $chatId, ?int $messageId): void
    {
        $postIt = PostIt::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('id', $postItId)->first();
        if (! $postIt) {
            $this->bot->answerCallbackQuery($tenant, $callbackId, '⛔ Request not found.', true);

            return;
        }

        if ($postIt->status === 'done') {
            $this->bot->answerCallbackQuery($tenant, $callbackId, '✅ Request already completed.');

            return;
        }

        $canComplete = $user->role === 'admin'
            || (int) $postIt->assignee_id === (int) $user->id
            || (int) $postIt->helper_id === (int) $user->id
            || ($user->role === 'coordinator' && (int) $postIt->department_id === (int) $user->department_id);

        if (! $canComplete) {
            $this->bot->answerCallbackQuery($tenant, $callbackId, '⛔ Unauthorized to complete this request.', true);

            return;
        }

        $wasDoing = ($postIt->status === 'doing');
        $isCanceled = ! $wasDoing;

        $postIt->status = 'done';
        $postIt->completed_at = now();
        $postIt->is_canceled = $isCanceled;
        if (! $wasDoing) {
            $postIt->bounty_points = 0;
        }
        $postIt->save();

        // Award points if work had started
        if ($wasDoing) {
            $calculator = app(BountyCalculator::class);
            $calculator->awardPayout($postIt->fresh());
        }

        // Recalculate capacity
        if ($postIt->assignee_id) {
            $assignee = User::withoutGlobalScopes()->find($postIt->assignee_id);
            if ($assignee) {
                $assignee->update([
                    'current_capacity' => (int) PostIt::withoutGlobalScopes()
                        ->where('assignee_id', $assignee->id)
                        ->whereIn('status', ['assigned', 'doing'])
                        ->sum('weight'),
                ]);
            }
        }
        if ($postIt->helper_id) {
            $helper = User::withoutGlobalScopes()->find($postIt->helper_id);
            if ($helper) {
                $helper->update([
                    'current_capacity' => (int) PostIt::withoutGlobalScopes()
                        ->where('assignee_id', $helper->id)
                        ->whereIn('status', ['assigned', 'doing'])
                        ->sum('weight'),
                ]);
            }
        }

        PostItTransfer::create([
            'post_it_id' => $postIt->id,
            'actor_id' => $user->id,
            'from_user_id' => $postIt->assignee_id,
            'to_user_id' => $postIt->assignee_id,
            'action' => $wasDoing ? 'completed' : 'canceled',
        ]);

        BoardUpdated::dispatch($tenant->id, $user->id);

        $resolutionType = $wasDoing ? 'done' : 'canceled';
        $this->bot->notifyRequesterOfResolution($postIt->fresh(), $resolutionType, $user);

        $pointsAwarded = $isCanceled ? 0 : $postIt->bounty_points;
        $this->bot->answerCallbackQuery($tenant, $callbackId, "✅ Completed #{$postIt->tracking_token}! (+{$pointsAwarded} pts)");

        if ($messageId) {
            $boardUrl = Edition::workspaceUrl($tenant, '/board');
            $newKeyboard = [
                [
                    ['text' => "✅ Completed by {$user->name}", 'url' => $boardUrl],
                ],
            ];
            $this->bot->editMessageReplyMarkup($tenant, $chatId, $messageId, $newKeyboard);
        }
    }

    /**
     * Handle availability toggle callback.
     */
    protected function handleAvailabilityToggle(Tenant $tenant, User $user, string $callbackId, int $targetUserId, string $chatId, ?int $messageId): void
    {
        if ($user->id !== $targetUserId && $user->role !== 'admin') {
            $this->bot->answerCallbackQuery($tenant, $callbackId, '⛔ Cannot toggle other users.', true);

            return;
        }

        $target = ($user->id === $targetUserId) ? $user : User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->findOrFail($targetUserId);
        $newAvailability = ! $target->is_available;
        $target->update(['is_available' => $newAvailability]);

        BoardUpdated::dispatch($tenant->id, $user->id);

        $statusText = $newAvailability ? '🟢 Available' : '🌙 Away';
        $this->bot->answerCallbackQuery($tenant, $callbackId, "Status updated: {$statusText}");

        $this->sendStatusMessage($tenant, $target, $chatId);
    }

    /**
     * Handle visibility mode callback.
     */
    protected function handleVisibilityChange(Tenant $tenant, User $user, string $callbackId, int $targetUserId, string $mode, string $chatId, ?int $messageId): void
    {
        if ($user->id !== $targetUserId && $user->role !== 'admin') {
            $this->bot->answerCallbackQuery($tenant, $callbackId, '⛔ Cannot alter other users.', true);

            return;
        }

        if (! in_array($mode, ['public', 'internal', 'private'], true)) {
            $this->bot->answerCallbackQuery($tenant, $callbackId, 'Invalid visibility mode.', true);

            return;
        }

        $target = ($user->id === $targetUserId) ? $user : User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->findOrFail($targetUserId);
        $target->update([
            'visibility' => $mode,
            'board_visibility' => $mode,
        ]);

        BoardUpdated::dispatch($tenant->id, $user->id);

        $this->bot->answerCallbackQuery($tenant, $callbackId, "Board visibility set to: {$mode}");

        $this->sendStatusMessage($tenant, $target, $chatId);
    }

    /**
     * Handle incoming text messages.
     *
     * @param  array<string, mixed>  $message
     */
    protected function handleMessage(array $message, ?Tenant $botTenant = null): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        $chatType = (string) ($message['chat']['type'] ?? 'private');
        $fromId = (string) ($message['from']['id'] ?? '');
        $fromUsername = (string) ($message['from']['username'] ?? '');
        $text = trim((string) ($message['text'] ?? ''));

        if ($text === '') {
            return;
        }

        // 1. Command /start
        if (str_starts_with($text, '/start')) {
            $this->handleStartCommand($text, $chatId, $fromUsername, $botTenant);

            return;
        }

        // 2. Command /link dept_{token} (group chat binding)
        if (str_starts_with($text, '/link')) {
            $this->handleLinkDeptCommand($text, $chatId, $botTenant);

            return;
        }

        // Resolve user by telegram_chat_id
        $userQuery = User::withoutGlobalScopes()->where('telegram_chat_id', $fromId);
        if ($botTenant) {
            $userQuery->where('tenant_id', $botTenant->id);
        }
        $user = $userQuery->first();

        if (! $user) {
            $tenant = $botTenant ?? Tenant::first();
            if ($tenant) {
                $this->bot->sendMessage(
                    $tenant,
                    $chatId,
                    "👋 <b>Welcome to Tantou Human Capacity OS!</b>\n\nYour Telegram account is not yet paired with a workspace user.\n\nTo link your account, log in to your Tantou board, open your user menu, and click <b>✈️ Telegram</b> to pair with 1 click."
                );
            }

            return;
        }

        $tenant = $user->tenant;
        if (! $tenant) {
            return;
        }

        app()->instance('currentTenant', $tenant);

        // 3. Command /status
        if (str_starts_with($text, '/status')) {
            $this->sendStatusMessage($tenant, $user, $chatId);

            return;
        }

        // 4. Command /my
        if (str_starts_with($text, '/my')) {
            $this->sendMyTasksMessage($tenant, $user, $chatId);

            return;
        }

        // 5. Command /new <title>
        if (str_starts_with($text, '/new')) {
            $title = trim(substr($text, 4));
            $this->handleNewTaskCommand($tenant, $user, $title, $chatId);

            return;
        }

        // 6. Default help
        $this->bot->sendMessage(
            $tenant,
            $chatId,
            "⚡ <b>Tantou Bot Commands:</b>\n\n"
            ."• <code>/status</code> — View live capacity, toggle Available/Away and visibility.\n"
            ."• <code>/my</code> — List your active in-progress tasks with action buttons.\n"
            .'• <code>/new [title]</code> — Quick-create a new request directly from Telegram.'
        );
    }

    /**
     * Handle /start [token] deep-link pairing.
     */
    protected function handleStartCommand(string $text, string $chatId, string $username, ?Tenant $botTenant): void
    {
        $param = trim(substr($text, 6));

        if (str_starts_with($param, 'usr_')) {
            $token = substr($param, 4);

            $query = User::where('telegram_link_token', $token);
            if ($botTenant) {
                $query->where('tenant_id', $botTenant->id);
            }

            $user = $query->first();
            if (! $user) {
                $fallbackTenant = $botTenant ?? Tenant::first();
                if ($fallbackTenant) {
                    $this->bot->sendMessage(
                        $fallbackTenant,
                        $chatId,
                        '⚠️ <b>Invalid or expired pairing link.</b> Please generate a fresh link from your Tantou board.'
                    );
                }

                return;
            }

            $user->update([
                'telegram_chat_id' => $chatId,
                'telegram_username' => filled($username) ? $username : null,
                'telegram_link_token' => null,
                'telegram_notifications_enabled' => true,
            ]);

            $tenant = $user->tenant;
            $this->bot->sendMessage(
                $tenant,
                $chatId,
                "✅ <b>Successfully Linked!</b>\n\n"
                .'Hello <b>'.htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8').'</b>, you are connected to <b>'.htmlspecialchars($tenant->name, ENT_QUOTES, 'UTF-8')."</b>.\n\n"
                .'Send <code>/status</code> to check your capacity, or <code>/my</code> to see your active tasks.'
            );

            return;
        }

        // Standard /start without token
        $userQuery = User::where('telegram_chat_id', $chatId);
        if ($botTenant) {
            $userQuery->where('tenant_id', $botTenant->id);
        }
        $existingUser = $userQuery->first();

        if ($existingUser && $existingUser->tenant) {
            $this->bot->sendMessage(
                $existingUser->tenant,
                $chatId,
                '👋 Welcome back <b>'.htmlspecialchars($existingUser->name, ENT_QUOTES, 'UTF-8').'</b> ('.htmlspecialchars($existingUser->tenant->name, ENT_QUOTES, 'UTF-8').")!\n\nSend <code>/status</code> or <code>/my</code> anytime."
            );
        } else {
            $fallbackTenant = $botTenant ?? Tenant::first();
            if ($fallbackTenant) {
                $this->bot->sendMessage(
                    $fallbackTenant,
                    $chatId,
                    "👋 <b>Welcome to Tantou Human Capacity OS!</b>\n\nTo link this Telegram account with your Tantou profile, open your Tantou board and click the <b>✈️ Telegram</b> button."
                );
            }
        }
    }

    /**
     * Handle /link dept_{token} command for group chat notifications.
     */
    protected function handleLinkDeptCommand(string $text, string $chatId, ?Tenant $botTenant): void
    {
        $parts = explode(' ', $text, 2);
        $param = trim($parts[1] ?? '');

        if (! str_starts_with($param, 'dept_')) {
            $fallback = $botTenant ?? Tenant::first();
            if ($fallback) {
                $this->bot->sendMessage($fallback, $chatId, 'Usage: <code>/link dept_{token}</code> to bind this group chat to a department.');
            }

            return;
        }

        $token = substr($param, 5);

        $query = Department::where('telegram_link_token', $token);
        if ($botTenant) {
            $query->where('tenant_id', $botTenant->id);
        }

        $dept = $query->first();
        if (! $dept) {
            $fallback = $botTenant ?? Tenant::first();
            if ($fallback) {
                $this->bot->sendMessage($fallback, $chatId, '⚠️ <b>Invalid or expired department link token.</b>');
            }

            return;
        }

        $dept->update([
            'telegram_chat_id' => $chatId,
            'telegram_link_token' => null,
        ]);

        $tenant = $dept->tenant;
        $this->bot->sendMessage(
            $tenant,
            $chatId,
            "✅ <b>Department Group Connected!</b>\n\nThis chat is now linked to <b>".htmlspecialchars($dept->name, ENT_QUOTES, 'UTF-8').'</b> ('.htmlspecialchars($tenant->name, ENT_QUOTES, 'UTF-8').").\nAll new incoming backlog requests for this department will be alerted here with interactive 🙋‍♂️ Claim buttons."
        );
    }

    /**
     * Send capacity & availability status message.
     */
    protected function sendStatusMessage(Tenant $tenant, User $user, string $chatId): void
    {
        $availText = $user->is_available ? '🟢 Working / Available' : '🌙 Away / Off-Duty';
        $visText = match ($user->board_visibility) {
            'internal' => '🔒 Internal (Team Only)',
            'private' => '🛡️ Private (Owner Only)',
            default => '🌐 Public (Clients & Team)',
        };

        $html = '👤 <b>Collaborator Status: '.htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8')."</b>\n"
            .'• Organization: <b>'.htmlspecialchars($tenant->name, ENT_QUOTES, 'UTF-8')."</b>\n"
            .'• Department: <b>'.htmlspecialchars($user->department?->name ?? 'General Pool', ENT_QUOTES, 'UTF-8')."</b>\n"
            ."• Live Capacity: <b>{$user->current_capacity} / {$user->max_capacity}</b>\n"
            ."• Availability: <b>{$availText}</b>\n"
            ."• Visibility: <b>{$visText}</b>\n"
            .'• Total Bounty: ⚡ <b>'.number_format($user->total_points).' pts</b>';

        $inlineKeyboard = [
            [
                ['text' => $user->is_available ? '🌙 Set Away' : '🟢 Set Available', 'callback_data' => "avail:{$tenant->id}:{$user->id}"],
            ],
            [
                ['text' => '🌐 Public', 'callback_data' => "vis:{$tenant->id}:{$user->id}:public"],
                ['text' => '🔒 Internal', 'callback_data' => "vis:{$tenant->id}:{$user->id}:internal"],
                ['text' => '🛡️ Private', 'callback_data' => "vis:{$tenant->id}:{$user->id}:private"],
            ],
            [
                ['text' => '🔗 Open Board', 'url' => Edition::workspaceUrl($tenant, '/board')],
            ],
        ];

        $this->bot->sendMessage($tenant, $chatId, $html, $inlineKeyboard);
    }

    /**
     * List user's active tasks.
     */
    protected function sendMyTasksMessage(Tenant $tenant, User $user, string $chatId): void
    {
        $activeTasks = PostIt::where('tenant_id', $tenant->id)
            ->where('assignee_id', $user->id)
            ->whereIn('status', ['doing', 'assigned'])
            ->orderBy('status', 'desc')
            ->orderBy('priority', 'desc')
            ->get();

        if ($activeTasks->isEmpty()) {
            $this->bot->sendMessage(
                $tenant,
                $chatId,
                "🎉 <b>Queue is clear!</b> You have no active tasks in progress.\nCapacity: <b>0 / {$user->max_capacity}</b>.",
                [
                    [['text' => '🔗 Open Board', 'url' => Edition::workspaceUrl($tenant, '/board')]],
                ]
            );

            return;
        }

        $this->bot->sendMessage(
            $tenant,
            $chatId,
            "📋 <b>Your Active Tasks ({$activeTasks->count()}):</b>"
        );

        foreach ($activeTasks as $task) {
            $statusBadge = $task->status === 'doing' ? '▶️ IN PROGRESS' : '⏳ ASSIGNED';
            $priorityUpper = strtoupper((string) $task->priority);

            if ($tenant->telegram_privacy_mode) {
                $itemText = "{$statusBadge} • <code>#{$task->tracking_token}</code>\n"
                    ."• Weight: <b>W{$task->weight}</b> • Priority: <b>{$priorityUpper}</b>";
            } else {
                $itemText = "{$statusBadge} • <b>".htmlspecialchars($task->title, ENT_QUOTES, 'UTF-8')."</b>\n"
                    ."• Code: <code>#{$task->tracking_token}</code> • Weight: <b>W{$task->weight}</b> • Priority: <b>{$priorityUpper}</b>";
            }

            $buttons = [];
            if ($task->status === 'assigned') {
                $buttons[] = ['text' => '▶️ Start', 'callback_data' => "start:{$tenant->id}:{$task->id}"];
            }
            $buttons[] = ['text' => '✅ Complete', 'callback_data' => "done:{$tenant->id}:{$task->id}"];

            $this->bot->sendMessage($tenant, $chatId, $itemText, [$buttons]);
        }
    }

    /**
     * Handle /new <title> command.
     */
    protected function handleNewTaskCommand(Tenant $tenant, User $user, string $title, string $chatId): void
    {
        if ($title === '') {
            $this->bot->sendMessage($tenant, $chatId, 'Usage: <code>/new [Task Title]</code>');

            return;
        }

        $weight = 2;
        $priority = 'normal';
        $bounty = 20;

        // If user is client or over capacity, send to backlog
        $assigneeId = null;
        $status = 'backlog';

        if (! $user->isClient() && ($user->current_capacity + $weight <= $user->max_capacity)) {
            $assigneeId = $user->id;
            $status = 'assigned';
        }

        $postIt = PostIt::create([
            'tenant_id' => $tenant->id,
            'department_id' => $user->department_id,
            'requester_id' => $user->id,
            'assignee_id' => $assigneeId,
            'assigned_by_id' => $assigneeId ? $user->id : null,
            'facility_name' => $user->facility_name,
            'title' => $title,
            'status' => $status,
            'priority' => $priority,
            'weight' => $weight,
            'bounty_points' => $bounty,
            'started_at' => null,
        ]);

        if ($assigneeId) {
            $user->update([
                'current_capacity' => (int) $user->assignedPostIts()->whereIn('status', ['assigned', 'doing'])->sum('weight'),
            ]);
        }

        PostItTransfer::create([
            'post_it_id' => $postIt->id,
            'actor_id' => $user->id,
            'from_user_id' => null,
            'to_user_id' => $assigneeId,
            'action' => $assigneeId ? 'assigned' : 'created',
        ]);

        BoardUpdated::dispatch($tenant->id, $user->id);

        $statusMsg = $assigneeId ? 'Assigned to your queue' : 'Placed in Backlog';
        $this->bot->sendMessage(
            $tenant,
            $chatId,
            '✨ <b>Task Created:</b> '.htmlspecialchars($title, ENT_QUOTES, 'UTF-8')."\n"
            ."• Code: <code>#{$postIt->tracking_token}</code>\n"
            ."• Status: <b>{$statusMsg}</b>\n"
            ."• Weight: <b>W{$weight}</b> (20 pts)",
            [
                [['text' => '🔗 Open Board', 'url' => Edition::workspaceUrl($tenant, '/board')]],
            ]
        );
    }
}
