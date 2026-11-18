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

namespace App\Mail;

use App\Models\PostIt;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PostItStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $actionUrl;

    public string $actorName;

    public string $orgName;

    public string $taskReference;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public PostIt $postIt,
        public string $resolutionType = 'done', // 'done' or 'canceled'
        public ?User $actor = null,
        public ?string $latestComment = null,
        ?string $actionUrl = null,
    ) {
        $tenant = $postIt->tenant;
        $this->orgName = $tenant?->name ?? config('app.name', 'Tantou');
        $this->taskReference = $postIt->tracking_token ?? ('#TNT-'.str_pad((string) $postIt->id, 4, '0', STR_PAD_LEFT));
        $this->actorName = $actor?->name ?? __('Team Member');

        if ($actionUrl) {
            $this->actionUrl = $actionUrl;
        } elseif (! empty($postIt->tracking_token)) {
            $this->actionUrl = $tenant ? Edition::workspaceUrl($tenant, '/track/'.$postIt->tracking_token) : url('/track/'.$postIt->tracking_token);
        } else {
            $this->actionUrl = $tenant ? Edition::workspaceUrl($tenant, '/board') : url('/board');
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $statusLabel = $this->resolutionType === 'canceled' ? __('Canceled') : __('Completed');

        return new Envelope(
            subject: __(':org: Request :ref :status (:title)', [
                'org' => $this->orgName,
                'ref' => $this->taskReference,
                'status' => $statusLabel,
                'title' => $this->postIt->title,
            ]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.post-it-status-changed',
        );
    }
}
