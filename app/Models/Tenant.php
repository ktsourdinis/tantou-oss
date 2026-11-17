<?php

namespace App\Models;

use App\Support\Edition;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'custom_domain',
    'plan',
    'billing_interval',
    'subscription_status',
    'stripe_customer_id',
    'stripe_subscription_id',
    'trial_ends_at',
    'current_period_ends_at',
    'settings',
    'strict_client_isolation',
    'public_submission_enabled',
    'timezone',
    'telegram_enabled',
    'telegram_bot_token',
    'telegram_bot_username',
    'telegram_webhook_secret',
    'telegram_privacy_mode',
    'telegram_update_offset',
])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'custom_domain',
        'plan',
        'billing_interval',
        'subscription_status',
        'stripe_customer_id',
        'stripe_subscription_id',
        'trial_ends_at',
        'current_period_ends_at',
        'settings',
        'strict_client_isolation',
        'public_submission_enabled',
        'timezone',
        'telegram_enabled',
        'telegram_bot_token',
        'telegram_bot_username',
        'telegram_webhook_secret',
        'telegram_privacy_mode',
        'telegram_update_offset',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'strict_client_isolation' => 'boolean',
            'public_submission_enabled' => 'boolean',
            'telegram_enabled' => 'boolean',
            'telegram_bot_token' => 'encrypted',
            'telegram_privacy_mode' => 'boolean',
            'telegram_update_offset' => 'integer',
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
        ];
    }

    /**
     * Determine if this is the public demo workspace.
     */
    public function isDemo(): bool
    {
        return $this->slug === 'hellenic-demo-ops';
    }

    /**
     * Resolve effective plan ('team', 'business').
     */
    public function effectivePlan(): string
    {
        if (Edition::isCommunity() || $this->isDemo()) {
            return 'business';
        }

        return $this->plan ?: 'team';
    }

    /**
     * Determine if the workspace currently has an active subscription or valid trial.
     */
    public function hasActiveSubscription(): bool
    {
        if (Edition::isCommunity() || $this->isDemo()) {
            return true;
        }

        return in_array($this->subscription_status, ['active', 'trialing'], true);
    }

    /**
     * Get configuration array for the effective plan.
     *
     * @return array<string, mixed>
     */
    public function planConfig(): array
    {
        $planKey = $this->effectivePlan();

        return config("tantou.plans.{$planKey}", config('tantou.plans.team', []));
    }

    /**
     * Maximum allowed team workers for the current plan (null = unlimited).
     */
    public function maxWorkersLimit(): ?int
    {
        if (Edition::isCommunity() || $this->isDemo()) {
            return null;
        }

        return $this->planConfig()['max_workers'] ?? null;
    }

    /**
     * Maximum allowed departments for the current plan (null = unlimited).
     */
    public function maxDepartmentsLimit(): ?int
    {
        if (Edition::isCommunity() || $this->isDemo()) {
            return null;
        }

        return $this->planConfig()['max_departments'] ?? null;
    }

    /**
     * Count currently active team workers (admins, coordinators, workers).
     */
    public function activeWorkersCount(): int
    {
        return $this->users()
            ->whereIn('role', ['admin', 'coordinator', 'collaborator', 'worker'])
            ->count();
    }

    /**
     * Determine if a new team worker can be added under current plan quotas.
     */
    public function canAddWorker(): bool
    {
        $limit = $this->maxWorkersLimit();
        if ($limit === null) {
            return true;
        }

        return $this->activeWorkersCount() < $limit;
    }

    /**
     * Check if a specific feature gate is unlocked for this tenant.
     */
    public function canUseFeature(string $featureKey): bool
    {
        if (Edition::isCommunity() || $this->isDemo()) {
            return true;
        }

        $features = $this->planConfig()['features'] ?? [];

        return (bool) ($features[$featureKey] ?? false);
    }

    /**
     * Get the departments that belong to the tenant.
     *
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * Get the users that belong to the tenant.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the post-its that belong to the tenant.
     *
     * @return HasMany<PostIt, $this>
     */
    public function postIts(): HasMany
    {
        return $this->hasMany(PostIt::class);
    }
}
