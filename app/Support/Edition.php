<?php

namespace App\Support;

use App\Cloud\CloudServiceProvider;
use App\Models\Tenant;

class Edition
{
    /**
     * Testing override for simulating editions.
     */
    protected static ?bool $forceCloud = null;

    /**
     * Determine if the application is running in Cloud (multi-tenant SaaS) mode.
     */
    public static function isCloud(): bool
    {
        if (static::$forceCloud !== null) {
            return static::$forceCloud;
        }

        return class_exists(CloudServiceProvider::class);
    }

    /**
     * Determine if the application is running in Self-Hosted (single-tenant) mode.
     */
    public static function isSelfHosted(): bool
    {
        return ! static::isCloud();
    }

    /**
     * Determine if new tenant organizations can be registered.
     */
    public static function canRegisterNewTenant(): bool
    {
        return static::isCloud() || Tenant::count() === 0;
    }

    /**
     * Resolve the base domain for the application.
     */
    public static function baseDomain(): string
    {
        return (string) (config('app.base_domain') ?? env('TANTOU_BASE_DOMAIN', 'localhost'));
    }

    /**
     * Override edition mode for test simulation.
     */
    public static function fake(?bool $isCloud): void
    {
        static::$forceCloud = $isCloud;
    }
}
