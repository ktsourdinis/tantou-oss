<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'tenant_id',
    'department_id',
    'role',
    'board_visibility',
    'is_available',
    'current_capacity',
    'max_capacity',
    'total_points',
    'locale',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'department_id',
        'role',
        'board_visibility',
        'is_available',
        'current_capacity',
        'max_capacity',
        'total_points',
        'locale',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'department_id' => 'integer',
            'is_available' => 'boolean',
            'current_capacity' => 'integer',
            'max_capacity' => 'integer',
            'total_points' => 'integer',
        ];
    }

    /**
     * Get the tenant that the user belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the department that the user belongs to.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the post-its assigned to the user.
     *
     * @return HasMany<PostIt, $this>
     */
    public function assignedPostIts(): HasMany
    {
        return $this->hasMany(PostIt::class, 'assignee_id')->smartOrder();
    }

    /**
     * Get the post-its requested by the user.
     *
     * @return HasMany<PostIt, $this>
     */
    public function requestedPostIts(): HasMany
    {
        return $this->hasMany(PostIt::class, 'requester_id');
    }

    /**
     * Get the post-its where the user acts as a co-op helper.
     *
     * @return HasMany<PostIt, $this>
     */
    public function helpedPostIts(): HasMany
    {
        return $this->hasMany(PostIt::class, 'helper_id');
    }

    /**
     * Get the comments written by this user.
     *
     * @return HasMany<PostItComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(PostItComment::class);
    }

    /**
     * Get the transfers initiated by this user.
     *
     * @return HasMany<PostItTransfer, $this>
     */
    public function initiatedTransfers(): HasMany
    {
        return $this->hasMany(PostItTransfer::class, 'actor_id');
    }

    /**
     * Get the attachments uploaded by this user.
     *
     * @return HasMany<PostItAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(PostItAttachment::class, 'uploader_id');
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is a coordinator.
     */
    public function isCoordinator(): bool
    {
        return $this->role === 'coordinator';
    }

    /**
     * Check if user is a worker/collaborator.
     */
    public function isWorker(): bool
    {
        return $this->role === 'worker';
    }

    /**
     * Check if user is a client.
     */
    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    /**
     * Determine if this user is authorized to change the board visibility mode of a target user.
     * Only admins (for any user within the tenant) or coordinators (for users within their managed department) are allowed.
     */
    public function canChangeBoardVisibilityOf(self $targetUser): bool
    {
        if ((int) $this->tenant_id !== (int) $targetUser->tenant_id) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isCoordinator() && $targetUser->department_id !== null && (int) $this->department_id === (int) $targetUser->department_id) {
            return true;
        }

        return false;
    }
}
