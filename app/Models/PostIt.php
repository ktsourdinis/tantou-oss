<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PostItFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
     * Get the file attachments on this post-it.
     *
     * @return HasMany<PostItAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(PostItAttachment::class);
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

    /**
     * Scope a query to sort post-its according to Tantou's smart deterministic queue ordering:
     * 1. Status rank: 'doing' (1) -> 'assigned' (2) -> 'backlog' (3) -> 'done' (4).
     * 2. Priority rank (active cards): 'urgent' (1) -> 'high' (2) -> 'normal' (3) -> 'low' (4).
     * 3. FIFO / SLA fairness tie-breaker:
     *    - Active cards ('doing', 'assigned', 'backlog'): created_at ASC, then id ASC.
     *    - Done cards ('done'): completed_at DESC, then id DESC.
     *
     * @param  Builder<PostIt>  $query
     * @return Builder<PostIt>
     */
    public function scopeSmartOrder(Builder $query): Builder
    {
        $status = $this->qualifyColumn('status');
        $priority = $this->qualifyColumn('priority');
        $completedAt = $this->qualifyColumn('completed_at');
        $updatedAt = $this->qualifyColumn('updated_at');
        $createdAt = $this->qualifyColumn('created_at');
        $id = $this->qualifyColumn('id');

        return $query
            ->orderByRaw("CASE {$status} WHEN 'doing' THEN 1 WHEN 'assigned' THEN 2 WHEN 'backlog' THEN 3 WHEN 'done' THEN 4 ELSE 5 END ASC")
            ->orderByRaw("CASE WHEN {$status} != 'done' THEN (CASE {$priority} WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 ELSE 5 END) ELSE 0 END ASC")
            ->orderByRaw("CASE WHEN {$status} = 'done' THEN COALESCE({$completedAt}, {$updatedAt}) END DESC")
            ->orderByRaw("CASE WHEN {$status} = 'done' THEN {$id} END DESC")
            ->orderByRaw("CASE WHEN {$status} != 'done' THEN {$createdAt} END ASC")
            ->orderByRaw("CASE WHEN {$status} != 'done' THEN {$id} END ASC");
    }

    /**
     * Sort an in-memory collection of PostIt models according to smart deterministic queue ordering.
     *
     * @param  Collection<int, PostIt>  $postIts
     * @return Collection<int, PostIt>
     */
    public static function sortCollection(Collection $postIts): Collection
    {
        return $postIts->sort(function (PostIt $a, PostIt $b): int {
            $statusRankA = match ($a->status) {
                'doing' => 1,
                'assigned' => 2,
                'backlog' => 3,
                'done' => 4,
                default => 5,
            };
            $statusRankB = match ($b->status) {
                'doing' => 1,
                'assigned' => 2,
                'backlog' => 3,
                'done' => 4,
                default => 5,
            };

            if ($statusRankA !== $statusRankB) {
                return $statusRankA <=> $statusRankB;
            }

            if ($a->status === 'done') {
                $completedA = $a->completed_at?->timestamp ?? $a->updated_at?->timestamp ?? 0;
                $completedB = $b->completed_at?->timestamp ?? $b->updated_at?->timestamp ?? 0;

                if ($completedA !== $completedB) {
                    return $completedB <=> $completedA;
                }

                return $b->id <=> $a->id;
            }

            $priorityRankA = match ($a->priority) {
                'urgent' => 1,
                'high' => 2,
                'normal' => 3,
                'low' => 4,
                default => 5,
            };
            $priorityRankB = match ($b->priority) {
                'urgent' => 1,
                'high' => 2,
                'normal' => 3,
                'low' => 4,
                default => 5,
            };

            if ($priorityRankA !== $priorityRankB) {
                return $priorityRankA <=> $priorityRankB;
            }

            $createdA = $a->created_at?->timestamp ?? 0;
            $createdB = $b->created_at?->timestamp ?? 0;

            if ($createdA !== $createdB) {
                return $createdA <=> $createdB;
            }

            return $a->id <=> $b->id;
        })->values();
    }
}
