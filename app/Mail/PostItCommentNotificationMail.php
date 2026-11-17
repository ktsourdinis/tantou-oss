<?php

namespace App\Mail;

use App\Models\PostIt;
use App\Models\PostItComment;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PostItCommentNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $actionUrl;

    public string $authorName;

    public string $orgName;

    public string $taskReference;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public PostIt $postIt,
        public PostItComment $comment,
        public ?User $author = null,
        ?string $actionUrl = null,
    ) {
        $tenant = $postIt->tenant;
        $this->orgName = $tenant?->name ?? config('app.name', 'Tantou');
        $this->taskReference = $postIt->tracking_token ?? ('#TNT-'.str_pad((string) $postIt->id, 4, '0', STR_PAD_LEFT));
        $this->authorName = $author?->name ?? $comment->authorDisplayName();

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
        return new Envelope(
            subject: __(':org: New comment on :ref (:title)', [
                'org' => $this->orgName,
                'ref' => $this->taskReference,
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
            view: 'emails.post-it-comment',
        );
    }
}
