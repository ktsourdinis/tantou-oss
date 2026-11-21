<?php

/**
 * Tantou (担当) — Human Capacity OS
 *
 * @author    Konstantinos Tsourdinis / HELLENIC WEB3 STUDIO
 * @copyright 2026 Konstantinos Tsourdinis / HELLENIC WEB3 STUDIO
 * @license   AGPL-3.0-or-later OR LicenseRef-Tantou-Commercial
 *
 * @link      https://github.com/ktsourdinis/tantou-oss
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later OR LicenseRef-Tantou-Commercial
 */

namespace App\Jobs;

use App\Models\PostIt;
use App\Services\TelegramBotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchPostItTelegramNotification implements ShouldQueue
{
    use Queueable;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * Maximum seconds the job can run before timing out.
     */
    public int $timeout = 20;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $postItId,
        public string $reason = 'created',
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TelegramBotService $bot): void
    {
        $postIt = PostIt::withoutGlobalScopes()
            ->with(['tenant', 'department', 'assignee', 'requester'])
            ->find($this->postItId);

        if (! $postIt) {
            return;
        }

        try {
            $bot->routePostItNotification($postIt, $this->reason);
        } catch (Throwable $e) {
            Log::warning('Queued Telegram notification failed', [
                'post_it_id' => $this->postItId,
                'reason' => $this->reason,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
