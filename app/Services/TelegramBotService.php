<?php

namespace App\Services;

use App\Mail\PostItCommentNotificationMail;
use App\Mail\PostItStatusChangedMail;
use App\Models\Department;
use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TelegramBotService
{
    /**
     * Resolve the active Telegram Bot Token for a given tenant.
     */
    public function resolveToken(?Tenant $tenant = null): ?string
    {
        if ($tenant && filled($tenant->telegram_bot_token)) {
            return (string) $tenant->telegram_bot_token;
        }

        $globalToken = config('tantou.telegram.bot_token');

        return filled($globalToken) ? (string) $globalToken : null;
    }

    /**
     * Resolve the active Telegram Bot Username for a given tenant.
     */
    public function resolveUsername(?Tenant $tenant = null): ?string
    {
        if ($tenant && filled($tenant->telegram_bot_username)) {
            return ltrim((string) $tenant->telegram_bot_username, '@');
        }

        $globalUsername = config('tantou.telegram.bot_username');

        return filled($globalUsername) ? ltrim((string) $globalUsername, '@') : null;
    }

    /**
     * Send an HTML formatted message via Telegram Bot API.
     *
     * @param  list<list<array<string, string>>>  $inlineKeyboard
     * @return array<string, mixed>|null
     */
    public function sendMessage(Tenant $tenant, string $chatId, string $htmlText, array $inlineKeyboard = []): ?array
    {
        $token = $this->resolveToken($tenant);
        if (! $token) {
            return null;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $htmlText,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if (! empty($inlineKeyboard)) {
            $payload['reply_markup'] = [
                'inline_keyboard' => $inlineKeyboard,
            ];
        }

        try {
            $response = Http::timeout(5)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Telegram sendMessage failed', [
                'tenant_id' => $tenant->id,
                'chat_id' => $chatId,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return null;
        } catch (Throwable $e) {
            Log::warning('Telegram sendMessage exception: '.$e->getMessage(), [
                'tenant_id' => $tenant->id,
                'chat_id' => $chatId,
            ]);

            return null;
        }
    }

    /**
     * Edit the inline keyboard of an existing message.
     *
     * @param  list<list<array<string, string>>>  $inlineKeyboard
     * @return array<string, mixed>|null
     */
    public function editMessageReplyMarkup(Tenant $tenant, string $chatId, int $messageId, array $inlineKeyboard = []): ?array
    {
        $token = $this->resolveToken($tenant);
        if (! $token) {
            return null;
        }

        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => [
                'inline_keyboard' => $inlineKeyboard,
            ],
        ];

        try {
            $response = Http::timeout(5)
                ->post("https://api.telegram.org/bot{$token}/editMessageReplyMarkup", $payload);

            return $response->successful() ? $response->json() : null;
        } catch (Throwable $e) {
            Log::warning('Telegram editMessageReplyMarkup exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Edit text and inline keyboard of an existing message.
     *
     * @param  list<list<array<string, string>>>  $inlineKeyboard
     * @return array<string, mixed>|null
     */
    public function editMessageText(Tenant $tenant, string $chatId, int $messageId, string $htmlText, array $inlineKeyboard = []): ?array
    {
        $token = $this->resolveToken($tenant);
        if (! $token) {
            return null;
        }

        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $htmlText,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if (! empty($inlineKeyboard)) {
            $payload['reply_markup'] = [
                'inline_keyboard' => $inlineKeyboard,
            ];
        }

        try {
            $response = Http::timeout(5)
                ->post("https://api.telegram.org/bot{$token}/editMessageText", $payload);

            return $response->successful() ? $response->json() : null;
        } catch (Throwable $e) {
            Log::warning('Telegram editMessageText exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Answer an incoming callback query from an inline button click.
     *
     * @return array<string, mixed>|null
     */
    public function answerCallbackQuery(Tenant $tenant, string $callbackQueryId, string $text = '', bool $showAlert = false): ?array
    {
        $token = $this->resolveToken($tenant);
        if (! $token) {
            return null;
        }

        try {
            $response = Http::timeout(5)
                ->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
                    'callback_query_id' => $callbackQueryId,
                    'text' => $text,
                    'show_alert' => $showAlert,
                ]);

            return $response->successful() ? $response->json() : null;
        } catch (Throwable $e) {
            Log::warning('Telegram answerCallbackQuery exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Fetch pending updates for polling transport.
     *
     * @return array<string, mixed>|null
     */
    public function getUpdates(?Tenant $tenant = null, int $offset = 0, int $limit = 100, int $timeout = 0): ?array
    {
        $token = $this->resolveToken($tenant);
        if (! $token) {
            return null;
        }

        try {
            $response = Http::timeout($timeout + 5)
                ->post("https://api.telegram.org/bot{$token}/getUpdates", [
                    'offset' => $offset,
                    'limit' => $limit,
                    'timeout' => $timeout,
                    'allowed_updates' => json_encode(['message', 'callback_query']),
                ]);

            return $response->successful() ? $response->json() : null;
        } catch (Throwable $e) {
            Log::warning('Telegram getUpdates exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Smart Targeted Telegram Routing for post-its:
     * - Case A — Assigned to a Specific Worker ($postIt->assignee_id !== null):
     *   Notify ONLY the assigned worker ($postIt->assignee->telegram_chat_id) with [ ▶️ Start Working ] and [ ✅ Complete ].
     *   NEVER send a duplicate notification to $department->telegram_chat_id when an assignee is set!
     * - Case B — Unassigned Backlog Task ($postIt->assignee_id === null):
     *   If the target department has a linked $department->telegram_chat_id, send the [ 🙋‍♂️ Claim ] alert there.
     *   Zero-Group Fallback: If the target department does NOT have a $department->telegram_chat_id,
     *   automatically send the [ 🙋‍♂️ Claim ] notification to the personal telegram_chat_id of all is_available = true
     *   workers belonging to that department (or workspace admins/coordinators if the department has no linked workers).
     */
    public function routePostItNotification(PostIt $postIt, string $reason = 'assigned'): void
    {
        $tenant = $postIt->tenant;
        if (! $tenant || ! $tenant->telegram_enabled) {
            return;
        }

        $assigneeId = $postIt->assignee_id;
        $assignee = $postIt->assignee ?? ($assigneeId ? User::find($assigneeId) : null);

        // Case A — Assigned to a Specific Worker ($postIt->assignee_id !== null)
        if ($assignee !== null) {
            if (filled($assignee->telegram_chat_id) && $assignee->telegram_notifications_enabled) {
                $this->notifyTaskAssigned($postIt, $reason);
            }

            // NEVER send a duplicate notification to $department->telegram_chat_id when assignee is set!
            return;
        }

        // Case B — Unassigned Backlog Task ($postIt->assignee_id === null)
        $this->notifyNewBacklogRequest($postIt);
    }

    /**
     * Alias for routePostItNotification.
     */
    public function notifyNewPostIt(PostIt $postIt, string $reason = 'assigned'): void
    {
        $this->routePostItNotification($postIt, $reason);
    }

    /**
     * Send notification alert for newly created Backlog requests.
     */
    public function notifyNewBacklogRequest(PostIt $postIt): void
    {
        $tenant = $postIt->tenant;
        if (! $tenant || ! $tenant->telegram_enabled) {
            return;
        }

        // Collect recipient chat IDs
        $chatIds = [];

        // 1. If target department has a linked telegram_chat_id (Group chat), send alert there!
        if ($postIt->department && filled($postIt->department->telegram_chat_id)) {
            $chatIds[] = $postIt->department->telegram_chat_id;
        } else {
            // Zero-Group Fallback:
            // Send to personal telegram_chat_id of all is_available = true workers in that department
            $departmentId = $postIt->department_id;
            $workerChatIds = [];

            if ($departmentId) {
                $workerChatIds = User::where('tenant_id', $tenant->id)
                    ->where('department_id', $departmentId)
                    ->whereIn('role', ['worker', 'collaborator'])
                    ->where('is_available', true)
                    ->whereNotNull('telegram_chat_id')
                    ->where('telegram_notifications_enabled', true)
                    ->pluck('telegram_chat_id')
                    ->all();
            }

            // If no available workers with Telegram linked in that department (or department has no linked workers),
            // fallback to workspace admins & coordinators of that department
            if (empty($workerChatIds)) {
                $adminCoordQuery = User::where('tenant_id', $tenant->id)
                    ->where(function ($q) use ($departmentId) {
                        $q->where('role', 'admin');
                        if ($departmentId) {
                            $q->orWhere(function ($q2) use ($departmentId) {
                                $q2->where('department_id', $departmentId)
                                    ->where('role', 'coordinator');
                            });
                        }
                    })
                    ->where('is_available', true)
                    ->whereNotNull('telegram_chat_id')
                    ->where('telegram_notifications_enabled', true);

                $workerChatIds = $adminCoordQuery->pluck('telegram_chat_id')->all();
            }

            $chatIds = $workerChatIds;
        }

        $chatIds = array_unique(array_filter($chatIds));

        if (empty($chatIds)) {
            return;
        }

        // Compose payload adhering to privacy mode
        $priorityUpper = strtoupper((string) $postIt->priority);
        $facility = filled($postIt->facility_name) ? htmlspecialchars($postIt->facility_name, ENT_QUOTES, 'UTF-8') : 'Direct';
        $deptName = $postIt->department ? htmlspecialchars($postIt->department->name, ENT_QUOTES, 'UTF-8') : 'General Pool';

        if ($tenant->telegram_privacy_mode) {
            $message = "🔒 <b>Protected Task</b>\n"
                ."• Code: <code>#{$postIt->tracking_token}</code>\n"
                ."• From: <b>{$facility}</b>\n"
                ."• Department: <b>{$deptName}</b>\n"
                ."• Priority: <b>{$priorityUpper}</b>\n"
                ."• Weight: <b>W{$postIt->weight}</b> (bounty: {$postIt->bounty_points} pts)";
        } else {
            $title = htmlspecialchars($postIt->title, ENT_QUOTES, 'UTF-8');
            $message = "📋 <b>New Backlog Request</b>\n"
                ."• Title: <b>{$title}</b>\n"
                ."• Code: <code>#{$postIt->tracking_token}</code>\n"
                ."• From: <b>{$facility}</b>\n"
                ."• Department: <b>{$deptName}</b>\n"
                ."• Priority: <b>{$priorityUpper}</b>\n"
                ."• Weight: <b>W{$postIt->weight}</b> (bounty: {$postIt->bounty_points} pts)";
        }

        $boardUrl = Edition::workspaceUrl($tenant, '/board');

        $inlineKeyboard = [
            [
                ['text' => '🙋‍♂️ Claim (Take Tantou)', 'callback_data' => "claim:{$tenant->id}:{$postIt->id}"],
                ['text' => '🔗 Open Board', 'url' => $boardUrl],
            ],
        ];

        foreach ($chatIds as $chatId) {
            $this->sendMessage($tenant, $chatId, $message, $inlineKeyboard);
        }
    }

    /**
     * Send notification alert when a card is assigned or transferred to a Collaborator.
     */
    public function notifyTaskAssigned(PostIt $postIt, string $reason = 'assigned'): void
    {
        $tenant = $postIt->tenant;
        if (! $tenant || ! $tenant->telegram_enabled) {
            return;
        }

        $assignee = $postIt->assignee ?? ($postIt->assignee_id ? User::find($postIt->assignee_id) : null);
        if (! $assignee || empty($assignee->telegram_chat_id) || ! $assignee->telegram_notifications_enabled) {
            return;
        }

        $priorityUpper = strtoupper((string) $postIt->priority);
        $facility = filled($postIt->facility_name) ? htmlspecialchars($postIt->facility_name, ENT_QUOTES, 'UTF-8') : 'Direct';

        $actionHeader = match ($reason) {
            'transferred' => '🔀 <b>Task Handed Off to You</b>',
            default => '🎯 <b>Task Assigned to You</b>',
        };

        if ($tenant->telegram_privacy_mode) {
            $message = "{$actionHeader} • 🔒 <b>Protected Task</b>\n"
                ."• Code: <code>#{$postIt->tracking_token}</code>\n"
                ."• From: <b>{$facility}</b>\n"
                ."• Priority: <b>{$priorityUpper}</b>\n"
                ."• Weight: <b>W{$postIt->weight}</b> (bounty: {$postIt->bounty_points} pts)\n"
                .'• Status: <b>'.strtoupper((string) $postIt->status).'</b>';
        } else {
            $title = htmlspecialchars($postIt->title, ENT_QUOTES, 'UTF-8');
            $message = "{$actionHeader}\n"
                ."• Title: <b>{$title}</b>\n"
                ."• Code: <code>#{$postIt->tracking_token}</code>\n"
                ."• From: <b>{$facility}</b>\n"
                ."• Priority: <b>{$priorityUpper}</b>\n"
                ."• Weight: <b>W{$postIt->weight}</b> (bounty: {$postIt->bounty_points} pts)\n"
                .'• Status: <b>'.strtoupper((string) $postIt->status).'</b>';
        }

        $boardUrl = Edition::workspaceUrl($tenant, '/board');

        $inlineButtons = [];
        if ($postIt->status === 'assigned') {
            $inlineButtons[] = ['text' => '▶️ Start Working', 'callback_data' => "start:{$tenant->id}:{$postIt->id}"];
            $inlineButtons[] = ['text' => '✅ Complete', 'callback_data' => "done:{$tenant->id}:{$postIt->id}"];
        } elseif ($postIt->status === 'doing') {
            $inlineButtons[] = ['text' => '✅ Complete', 'callback_data' => "done:{$tenant->id}:{$postIt->id}"];
        }
        $inlineButtons[] = ['text' => '🔗 Open Board', 'url' => $boardUrl];

        $inlineKeyboard = [
            $inlineButtons,
        ];

        // Send alert ONLY to the assigned worker's private chat (does not spam department group)
        $this->sendMessage($tenant, (string) $assignee->telegram_chat_id, $message, $inlineKeyboard);
    }

    /**
     * Notify relevant parties when a micro-comment is posted on a Post-It:
     * - Exclude the comment author so nobody notifies themselves.
     * - If written by Worker / Admin / Staff -> Notify Requester:
     *   - If $postIt->creator (or requester User) has telegram_chat_id -> send Telegram message.
     *   - Email Fallback: Otherwise, if requester User has an email OR guest Post-It has requester_email -> send PostItCommentNotificationMail.
     * - If written by Requester / Client / Guest -> Notify Assigned Worker ($postIt->user) & Co-op Collaborator ($postIt->collaborator):
     *   - For each worker: if telegram_chat_id is set -> send Telegram notification; otherwise fallback to sending PostItCommentNotificationMail to $worker->email.
     */
    public function notifyCommentAdded(PostIt $postIt, mixed $comment, ?User $author = null): void
    {
        $tenant = $postIt->tenant ?? ($postIt->tenant_id ? Tenant::find($postIt->tenant_id) : null);

        // Resolve author if not directly provided
        if (! $author && ! empty($comment->user_id)) {
            $author = $comment->user ?? User::find($comment->user_id);
        }

        $authorId = $author?->id;
        $authorName = $author?->name ?? ($comment->guest_name ?: __('Guest'));
        $taskRef = ! empty($postIt->tracking_token) ? "#{$postIt->tracking_token}" : ('#TNT-'.str_pad((string) $postIt->id, 4, '0', STR_PAD_LEFT));
        $trackUrl = ! empty($postIt->tracking_token)
            ? ($tenant ? Edition::workspaceUrl($tenant, '/track/'.$postIt->tracking_token) : url('/track/'.$postIt->tracking_token))
            : ($tenant ? Edition::workspaceUrl($tenant, '/board') : url('/board'));
        $boardUrl = $tenant ? Edition::workspaceUrl($tenant, '/board') : url('/board');

        // Determine if author is the Requester/Client/Guest OR Internal Staff
        $isRequesterAuthor = false;
        if (! $author) {
            // Guest comment from public tracker
            $isRequesterAuthor = true;
        } elseif ($postIt->requester_id && (int) $author->id === (int) $postIt->requester_id) {
            $isRequesterAuthor = true;
        } elseif ($author->role === 'client') {
            $isRequesterAuthor = true;
        }

        $commentBody = (string) ($comment->body ?? '');
        $escapedBody = htmlspecialchars($commentBody, ENT_QUOTES, 'UTF-8');

        if (! $isRequesterAuthor) {
            // Case 1: Written by Worker / Admin -> Notify Requester
            $requester = $postIt->creator ?? $postIt->requester ?? ($postIt->requester_id ? User::find($postIt->requester_id) : null);

            // Don't notify if author is somehow the requester
            if ($requester && $authorId && (int) $requester->id === (int) $authorId) {
                return;
            }

            // 1. Telegram First
            if ($tenant && $tenant->telegram_enabled && $requester && filled($requester->telegram_chat_id) && ($requester->telegram_notifications_enabled ?? true)) {
                if ($tenant->telegram_privacy_mode) {
                    $tgMessage = "💬 <b>New comment on {$taskRef}</b> from <b>{$authorName}</b>\n<i>[Content protected by privacy mode]</i>";
                } else {
                    $tgMessage = "💬 <b>New comment on {$taskRef}</b> from <b>{$authorName}</b>:\n\"{$escapedBody}\"";
                }

                $keyboard = [
                    [
                        ['text' => '🔍 View Request', 'url' => $trackUrl],
                    ],
                ];

                $this->sendMessage($tenant, (string) $requester->telegram_chat_id, $tgMessage, $keyboard);

                return;
            }

            // 2. Email Fallback
            $targetEmail = $requester?->email ?? $postIt->requester_email;
            if (filled($targetEmail)) {
                try {
                    Mail::to($targetEmail)->send(new PostItCommentNotificationMail($postIt, $comment, $author, $trackUrl));
                } catch (Throwable $e) {
                    Log::warning('Failed sending PostItCommentNotificationMail: '.$e->getMessage(), [
                        'post_it_id' => $postIt->id,
                        'email' => $targetEmail,
                    ]);
                }
            }
        } else {
            // Case 2: Written by Requester / Client / Guest -> Notify Assigned Worker & Co-op Collaborator
            $assignee = $postIt->assignee ?? ($postIt->assignee_id ? User::find($postIt->assignee_id) : null);
            $helper = $postIt->helper ?? ($postIt->helper_id ? User::find($postIt->helper_id) : null);

            $workers = collect([$assignee, $helper])->filter()->unique('id');

            foreach ($workers as $worker) {
                if ($authorId && (int) $worker->id === (int) $authorId) {
                    continue;
                }

                // 1. Telegram First
                if ($tenant && $tenant->telegram_enabled && filled($worker->telegram_chat_id) && ($worker->telegram_notifications_enabled ?? true)) {
                    if ($tenant->telegram_privacy_mode) {
                        $tgMessage = "💬 <b>New comment on {$taskRef}</b> from <b>{$authorName}</b>\n<i>[Content protected by privacy mode]</i>";
                    } else {
                        $tgMessage = "💬 <b>New comment on {$taskRef}</b> from <b>{$authorName}</b>:\n\"{$escapedBody}\"";
                    }

                    $keyboard = [
                        [
                            ['text' => '🔗 Open Board', 'url' => $boardUrl],
                        ],
                    ];

                    $this->sendMessage($tenant, (string) $worker->telegram_chat_id, $tgMessage, $keyboard);
                } elseif (filled($worker->email)) {
                    // 2. Email Fallback
                    try {
                        Mail::to($worker->email)->send(new PostItCommentNotificationMail($postIt, $comment, $author, $boardUrl));
                    } catch (Throwable $e) {
                        Log::warning('Failed sending PostItCommentNotificationMail to worker: '.$e->getMessage(), [
                            'post_it_id' => $postIt->id,
                            'worker_id' => $worker->id,
                            'email' => $worker->email,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Notify the requester when a Post-It is resolved (completed or canceled):
     * - Do NOT notify if the actor closing the request is the requester themselves.
     * - Delivery Cascade for the Requester:
     *   1. If the requester is a registered User and has a non-empty telegram_chat_id -> send Telegram notification.
     *   2. Email Fallback: Otherwise, determine requester's email ($postIt->creator?->email ?? $postIt->requester_email)
     *      and send PostItStatusChangedMail.
     */
    public function notifyRequesterOfResolution(PostIt $postIt, string $resolutionType = 'done', ?User $actor = null): void
    {
        // Do NOT notify if the actor closing the request is the requester themselves
        if ($actor && $postIt->requester_id && (int) $actor->id === (int) $postIt->requester_id) {
            return;
        }

        // Do NOT notify if self-closed via guest tracker action
        if ($resolutionType === 'closed_by_requester' || $postIt->isClosedByRequester()) {
            return;
        }

        $tenant = $postIt->tenant ?? ($postIt->tenant_id ? Tenant::find($postIt->tenant_id) : null);
        $requester = $postIt->creator ?? $postIt->requester ?? ($postIt->requester_id ? User::find($postIt->requester_id) : null);

        $taskRef = ! empty($postIt->tracking_token) ? "#{$postIt->tracking_token}" : ('#TNT-'.str_pad((string) $postIt->id, 4, '0', STR_PAD_LEFT));
        $actorName = $actor?->name ?? __('Team Member');
        $trackUrl = ! empty($postIt->tracking_token)
            ? ($tenant ? Edition::workspaceUrl($tenant, '/track/'.$postIt->tracking_token) : url('/track/'.$postIt->tracking_token))
            : ($tenant ? Edition::workspaceUrl($tenant, '/board') : url('/board'));

        $isCanceled = ($resolutionType === 'canceled' || $postIt->is_canceled);

        // 1. Telegram First
        if ($tenant && $tenant->telegram_enabled && $requester && filled($requester->telegram_chat_id) && ($requester->telegram_notifications_enabled ?? true)) {
            if ($tenant->telegram_privacy_mode) {
                if ($isCanceled) {
                    $tgMessage = "🚫 <b>Your request {$taskRef} was canceled</b> by <b>{$actorName}</b>.";
                } else {
                    $tgMessage = "✅ <b>Your request {$taskRef} has been completed</b> by <b>{$actorName}</b>!";
                }
            } else {
                $escapedTitle = htmlspecialchars($postIt->title, ENT_QUOTES, 'UTF-8');
                if ($isCanceled) {
                    $tgMessage = "🚫 <b>Your request {$taskRef} (\"{$escapedTitle}\") was canceled</b> by <b>{$actorName}</b>.";
                } else {
                    $tgMessage = "✅ <b>Your request {$taskRef} (\"{$escapedTitle}\") has been completed</b> by <b>{$actorName}</b>!";
                }
            }

            $keyboard = [
                [
                    ['text' => '🔍 View Request', 'url' => $trackUrl],
                ],
            ];

            $this->sendMessage($tenant, (string) $requester->telegram_chat_id, $tgMessage, $keyboard);

            return;
        }

        // 2. Email Fallback
        $targetEmail = $requester?->email ?? $postIt->requester_email;
        if (filled($targetEmail)) {
            $latestComment = $postIt->comments()->latest('id')->first()?->body;

            try {
                Mail::to($targetEmail)->send(new PostItStatusChangedMail(
                    $postIt,
                    $isCanceled ? 'canceled' : 'done',
                    $actor,
                    $latestComment,
                    $trackUrl,
                ));
            } catch (Throwable $e) {
                Log::warning('Failed sending PostItStatusChangedMail: '.$e->getMessage(), [
                    'post_it_id' => $postIt->id,
                    'email' => $targetEmail,
                ]);
            }
        }
    }
}
