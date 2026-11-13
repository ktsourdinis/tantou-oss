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
    'current_capacity',
    'max_capacity',
    'total_points',
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
        'current_capacity',
        'max_capacity',
        'total_points',
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
        return $this->hasMany(PostIt::class, 'assignee_id');
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
}
