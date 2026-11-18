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
    'email_verified_at',
    'password',
    'tenant_id',
    'department_id',
    'role',
    'facility_name',
    'visibility',
    'board_visibility',
    'is_available',
    'current_capacity',
    'max_capacity',
    'total_points',
    'locale',
    'timezone',
    'telegram_chat_id',
    'telegram_username',
    'telegram_link_token',
    'telegram_notifications_enabled',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'visibility' => 'public',
        'board_visibility' => 'public',
        'is_available' => true,
        'current_capacity' => 0,
        'max_capacity' => 10,
        'total_points' => 0,
        'locale' => 'en',
        'timezone' => 'Europe/Athens',
        'is_superadmin' => false,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'tenant_id',
        'department_id',
        'role',
        'facility_name',
        'visibility',
        'board_visibility',
        'is_available',
        'current_capacity',
        'max_capacity',
        'total_points',
        'locale',
        'timezone',
        'telegram_chat_id',
        'telegram_username',
        'telegram_link_token',
        'telegram_notifications_enabled',
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if (empty($user->visibility) && ! empty($user->board_visibility)) {
                $user->visibility = $user->board_visibility;
            } elseif (empty($user->board_visibility) && ! empty($user->visibility)) {
                $user->board_visibility = $user->visibility;
            }
        });
    }

    /**
     * Set the visibility attribute, keeping board_visibility in sync.
     */
    public function setVisibilityAttribute(?string $value): void
    {
        $val = $value ?: 'public';
        $this->attributes['visibility'] = $val;
        $this->attributes['board_visibility'] = $val;
    }

    /**
     * Set the board_visibility attribute, keeping visibility in sync.
     */
    public function setBoardVisibilityAttribute(?string $value): void
    {
        $val = $value ?: 'public';
        $this->attributes['board_visibility'] = $val;
        $this->attributes['visibility'] = $val;
    }

    /**
     * Get the visibility attribute with fallback to board_visibility.
     */
    public function getVisibilityAttribute(?string $value): string
    {
        return $value ?: ($this->attributes['board_visibility'] ?? 'public');
    }

    /**
     * Get the board_visibility attribute with fallback to visibility.
     */
    public function getBoardVisibilityAttribute(?string $value): string
    {
        return $this->attributes['visibility'] ?? $value ?? 'public';
    }

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
            'telegram_notifications_enabled' => 'boolean',
            'is_superadmin' => 'boolean',
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
     * Check if user is a platform superadmin.
     */
    public function isSuperAdmin(): bool
    {
        $email = strtolower(trim((string) ($this->attributes['email'] ?? '')));

        if (
            $email === ''
            || str_ends_with($email, '@tantou.local')
            || str_ends_with($email, '@hellenic-demo.gr')
        ) {
            return false;
        }

        if (! (bool) ($this->attributes['is_superadmin'] ?? false)) {
            return false;
        }

        $singleEmail = strtolower(trim((string) (
            config('tantou-cloud.superadmin_email')
            ?: config('tantou.superadmin_email')
            ?: env('TANTOU_SUPERADMIN_EMAIL', '')
        )));

        $configuredList = trim((string) (
            config('tantou-cloud.superadmin_emails')
            ?: config('tantou.superadmin_emails')
            ?: env('SUPERADMIN_EMAIL', '')
        ));

        if ($singleEmail !== '' || $configuredList !== '') {
            $rawItems = array_merge(
                $singleEmail !== '' ? [$singleEmail] : [],
                $configuredList !== '' ? explode(',', $configuredList) : []
            );

            $allowedEmails = array_values(array_filter(
                array_map(
                    static fn (string $item): string => strtolower(trim($item)),
                    $rawItems
                ),
                static fn (string $item): bool => $item !== ''
                    && ! str_ends_with($item, '@tantou.local')
                    && ! str_ends_with($item, '@hellenic-demo.gr')
            ));

            return in_array($email, $allowedEmails, true);
        }

        return true;
    }

    /**
     * Accessor for is_superadmin attribute so property access also enforces demo account exclusion and allowlist.
     */
    public function getIsSuperadminAttribute(mixed $value): bool
    {
        return $this->isSuperAdmin();
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

    /**
     * Determine if this user is permitted to assign tasks to a target collaborator based on firewall & column visibility rules.
     */
    public function canAssignTo(self $targetCollaborator): bool
    {
        if ((int) $this->tenant_id !== (int) $targetCollaborator->tenant_id) {
            return false;
        }

        $targetVisibility = $targetCollaborator->visibility ?: ($targetCollaborator->board_visibility ?: 'public');

        if ($targetVisibility === 'none') {
            return false;
        }

        if ($targetVisibility === 'public') {
            return true;
        }

        // Clients can ONLY assign to public columns (Firewall)
        if ($this->isClient()) {
            return false;
        }

        if ($targetVisibility === 'internal') {
            return true;
        }

        if ($targetVisibility === 'private') {
            if ($this->isAdmin()) {
                return true;
            }

            if ((int) $this->id === (int) $targetCollaborator->id) {
                return true;
            }

            if ($this->isCoordinator() && $targetCollaborator->department_id !== null && (int) $this->department_id === (int) $targetCollaborator->department_id) {
                return true;
            }

            return false;
        }

        return false;
    }

    /**
     * Get the effective timezone for this user (inheriting from tenant or system default).
     */
    public function effectiveTimezone(): string
    {
        return $this->timezone ?: ($this->tenant?->timezone ?: config('app.timezone', 'Europe/Athens'));
    }
}
