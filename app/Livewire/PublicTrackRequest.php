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

namespace App\Livewire;

use App\Events\BoardUpdated;
use App\Models\PostIt;
use App\Models\PostItTransfer;
use App\Models\User;
use App\Services\BountyCalculator;
use App\Services\TelegramBotService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class PublicTrackRequest extends Component
{
    #[Locked]
    public string $token = '';

    #[Locked]
    public ?int $postItId = null;

    /**
     * Honeypot field (bots fill this, humans leave empty).
     */
    public string $website_url = '';

    #[Validate('required|string|min:2|max:1000')]
    public string $newCommentBody = '';

    public string $guestCloseReason = '';

    public bool $showClosePrompt = false;

    /**
     * Resolve the tracked PostIt model scoped strictly to the locked tracking token.
     *
     * @param  array<int, string|\Closure>  $with
     */
    private function resolveTrackedPostIt(array $with = []): PostIt
    {
        $query = PostIt::withoutGlobalScopes()
            ->where('tracking_token', $this->token)
            ->when($this->postItId, fn ($q) => $q->where('id', $this->postItId));

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->firstOrFail();
    }

    /**
     * Mount the tracker with the given tracking token.
     */
    public function mount(string $token): void
    {
        $this->token = trim($token);
        $postIt = $this->resolveTrackedPostIt();
        $this->postItId = $postIt->id;

        if ($postIt->tenant) {
            app()->instance('currentTenant', $postIt->tenant);
        }
    }

    /**
     * Rebind currentTenant during Livewire hydration.
     */
    public function hydrate(): void
    {
        if (isset($this->postItId)) {
            $postIt = $this->resolveTrackedPostIt();
            if ($postIt && $postIt->tenant) {
                app()->instance('currentTenant', $postIt->tenant);
            }
        }
    }

    /**
     * Add a follow-up comment as the guest requester.
     */
    public function addComment(): void
    {
        // Honeypot check
        if (! empty($this->website_url)) {
            $this->addError('newCommentBody', __('Spam submission detected.'));

            return;
        }

        // Rate Limiter: Max 5 submissions per 10 minutes per IP
        $throttleKey = 'public-track|'.request()->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('newCommentBody', __('Too many requests. Please try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]));

            return;
        }

        $this->validate();

        $postIt = $this->resolveTrackedPostIt();

        if ($postIt->status === 'done') {
            $this->addError('newCommentBody', __('Cannot add comments to a completed request.'));

            return;
        }

        RateLimiter::hit($throttleKey, 600);

        $authorName = ($postIt->guest_name ?: __('Guest')).' ('.__('Guest').')';

        $comment = $postIt->comments()->create([
            'user_id' => null,
            'guest_name' => $authorName,
            'body' => trim($this->newCommentBody),
        ]);

        app(TelegramBotService::class)->notifyCommentAdded($postIt, $comment, null);

        $this->newCommentBody = '';
        $this->resetValidation('newCommentBody');

        BoardUpdated::dispatch($postIt->tenant_id);
    }

    /**
     * Alias for addComment.
     */
    public function submitReply(): void
    {
        $this->addComment();
    }

    /**
     * Self-close the request as the guest requester via tracking link.
     */
    public function closeByGuest(?string $reason = null): void
    {
        // Honeypot check
        if (! empty($this->website_url)) {
            return;
        }

        // Rate Limiter: Max 5 submissions per 10 minutes per IP
        $throttleKey = 'public-track|'.request()->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return;
        }

        $postIt = $this->resolveTrackedPostIt();

        if ($postIt->status === 'done') {
            return;
        }

        RateLimiter::hit($throttleKey, 600);

        $wasInProgress = ($postIt->status === 'doing');
        $assigneeId = $postIt->assignee_id;
        $closureReason = trim((string) ($reason ?: $this->guestCloseReason));
        $effectiveReason = filled($closureReason) ? $closureReason : __('Issue resolved / No longer needed');

        if ($wasInProgress) {
            $postIt->update([
                'status' => 'done',
                'is_canceled' => false,
                'completed_at' => now(),
            ]);

            $calculator = app(BountyCalculator::class);
            $calculator->awardPayout($postIt->fresh());
        } else {
            // Not started yet (backlog or assigned): complete with 0 bounty points so unearned points are not farmed
            $postIt->update([
                'status' => 'done',
                'is_canceled' => true,
                'completed_at' => now(),
                'bounty_points' => 0,
            ]);
        }

        // Recalculate capacity for affected worker
        if ($assigneeId !== null) {
            $assignee = User::find($assigneeId);
            if ($assignee !== null) {
                $assignee->update([
                    'current_capacity' => (int) PostIt::where('assignee_id', $assigneeId)
                        ->whereIn('status', ['assigned', 'doing'])
                        ->sum('weight'),
                ]);
            }
        }

        // Audit Trail: transfer history
        PostItTransfer::create([
            'post_it_id' => $postIt->id,
            'actor_id' => null,
            'from_user_id' => $assigneeId,
            'to_user_id' => $assigneeId,
            'action' => 'closed_by_requester',
            'created_at' => now(),
        ]);

        // Audit Trail: comment
        $authorName = ($postIt->guest_name ?: __('Guest')).' ('.__('Guest').')';
        $commentBody = '✅ '.__('Closed by requester (:name): :reason', [
            'name' => $authorName,
            'reason' => $effectiveReason,
        ]);

        $postIt->comments()->create([
            'user_id' => null,
            'guest_name' => $authorName,
            'body' => $commentBody,
        ]);

        $this->showClosePrompt = false;
        $this->guestCloseReason = '';

        BoardUpdated::dispatch($postIt->tenant_id);
    }

    /**
     * Alias for closeByGuest.
     */
    public function closeByRequester(?string $reason = null): void
    {
        $this->closeByGuest($reason);
    }

    /**
     * Render the tracking view.
     */
    public function render(): View
    {
        $postIt = $this->resolveTrackedPostIt([
            'tenant',
            'department',
            'assignee',
            'comments' => fn ($query) => $query->oldest('id'),
            'comments.user',
            'attachments',
        ]);

        $queuePosition = null;
        $queueTotal = null;

        if ($postIt->assignee_id && in_array($postIt->status, ['assigned', 'doing'], true)) {
            $activeQueue = PostIt::withoutGlobalScopes()->where('assignee_id', $postIt->assignee_id)
                ->whereIn('status', ['assigned', 'doing'])
                ->smartOrder()
                ->pluck('id')
                ->all();

            $index = array_search($postIt->id, $activeQueue, true);
            if ($index !== false) {
                $queuePosition = $index + 1;
                $queueTotal = count($activeQueue);
            }
        }

        $step = match ($postIt->status) {
            'doing' => 3,
            'done' => 4,
            'assigned' => 2,
            default => 1,
        };

        return view('livewire.public-track-request', [
            'postIt' => $postIt,
            'queuePosition' => $queuePosition,
            'queueTotal' => $queueTotal,
            'step' => $step,
        ]);
    }
}
