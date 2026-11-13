<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PostItFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'department_id',
    'requester_id',
    'assignee_id',
    'assigned_by_id',
    'helper_id',
    'helper_split',
    'title',
    'description',
    'status',
    'priority',
    'weight',
    'bounty_points',
    'started_at',
    'completed_at',
])]
class PostIt extends Model
{
    /** @use HasFactory<PostItFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'department_id',
        'requester_id',
        'assignee_id',
        'assigned_by_id',
        'helper_id',
        'helper_split',
        'title',
        'description',
        'status',
        'priority',
        'weight',
        'bounty_points',
        'started_at',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'assigned_by_id' => 'integer',
            'helper_id' => 'integer',
            'helper_split' => 'integer',
            'weight' => 'integer',
            'bounty_points' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the tenant that owns the post-it.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the department categorized under this post-it.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user who requested the post-it.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Get the user assigned to the post-it (the Tantou).
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Get the user who assigned or transferred this post-it.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    /**
     * Get the co-op helper assigned to assist with this post-it.
     *
     * @return BelongsTo<User, $this>
     */
    public function helper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'helper_id');
    }

    /**
     * Get the comments on this post-it.
     *
     * @return HasMany<PostItComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(PostItComment::class);
    }

    /**
     * Get the handoff and assignment transfers trail.
     *
     * @return HasMany<PostItTransfer, $this>
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(PostItTransfer::class)->oldest('id');
    }

    /**
     * Get the ordered chain of collaborator names this card has passed through.
     *
     * @return list<string>
     */
    public function getAssignmentChain(): array
    {
        $chain = [];
        $lastId = null;

        foreach ($this->transfers as $transfer) {
            if (in_array($transfer->action, ['assigned', 'transferred'], true) && $transfer->to_user_id !== null) {
                if ($transfer->to_user_id !== $lastId) {
                    $name = $transfer->toUser?->name;
                    if ($name) {
                        $chain[] = explode(' ', trim($name))[0];
                    }
                    $lastId = $transfer->to_user_id;
                }
            }
        }

        return $chain;
    }

    /**
     * Backward-compatible accessor for complexity_score mapping to bounty_points.
     */
    public function getComplexityScoreAttribute(): int
    {
        return (int) ($this->attributes['bounty_points'] ?? 0);
    }

    /**
     * Backward-compatible mutator for complexity_score mapping to bounty_points.
     */
    public function setComplexityScoreAttribute(?int $value): void
    {
        $this->attributes['bounty_points'] = $value ?? 0;
    }
}
