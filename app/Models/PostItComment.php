<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'post_it_id',
    'user_id',
    'guest_name',
    'body',
])]
class PostItComment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'post_it_id',
        'user_id',
        'guest_name',
        'body',
    ];

    /**
     * Get the post-it that the comment belongs to.
     *
     * @return BelongsTo<PostIt, $this>
     */
    public function postIt(): BelongsTo
    {
        return $this->belongsTo(PostIt::class);
    }

    /**
     * Get the user who authored the comment.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the display name for the comment author.
     */
    public function authorDisplayName(): string
    {
        return $this->user?->name ?? $this->guest_name ?? __('Anonymous');
    }
}
