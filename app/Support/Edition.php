<?php

namespace App\Support;

use App\Cloud\CloudServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class Edition
{
    /**
     * Reserved subdomains and URL slugs.
     *
     * @var list<string>
     */
    public const array RESERVED_SLUGS = [
        'www',
        'app',
        'api',
        'admin',
        'mail',
        'status',
        'billing',
        'auth',
        'login',
        'register',
        'cloud',
    ];

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
        $domain = (string) (config('app.base_domain') ?? env('TANTOU_BASE_DOMAIN', 'localhost'));

        if (str_contains($domain, '://')) {
            $parsed = parse_url($domain, PHP_URL_HOST);
            if ($parsed) {
                return $parsed;
            }
        }

        return trim(explode(':', $domain)[0]);
    }

    /**
     * Construct the full URL for a tenant's subdomain, preserving scheme and port from the request.
     */
    public static function tenantUrl(Tenant $tenant, string $path = '/board', ?Request $request = null): string
    {
        $request = $request ?? request();
        $baseDomain = static::baseDomain();

        $scheme = $request ? $request->getScheme() : 'http';
        $port = $request ? $request->getPort() : 80;
        $portString = ($port && ! in_array($port, [80, 443], true)) ? ":{$port}" : '';

        $pathStr = $path !== '' ? ('/'.ltrim($path, '/')) : '';

        return "{$scheme}://{$tenant->slug}.{$baseDomain}{$portString}{$pathStr}";
    }

    /**
     * Generate a temporary signed auto-login URL on the tenant's subdomain.
     */
    public static function signedTenantAutoLoginUrl(User $user, ?Request $request = null, int $minutes = 15): string
    {
        $tenant = $user->tenant;
        if (! $tenant) {
            throw new \InvalidArgumentException("User {$user->id} does not have an associated tenant organization.");
        }

        $targetRoot = static::tenantUrl($tenant, '', $request);

        URL::forceRootUrl($targetRoot);

        try {
            return URL::temporarySignedRoute(
                'cloud.autologin',
                now()->addMinutes($minutes),
                ['user' => $user->id]
            );
        } finally {
            URL::forceRootUrl(null);
        }
    }

    /**
     * Generate guaranteed-available subdomain slug suggestions.
     *
     * @return list<string>
     */
    public static function suggestAvailableSlugs(string $base, ?int $ignoreTenantId = null, int $count = 3): array
    {
        $cleanBase = Str::slug($base);
        if ($cleanBase === '') {
            $cleanBase = 'workspace';
        }

        $suffixes = ['hq', 'ops', 'team', 'work', 'app', 'hub', 'space', 'lab', 'co', 'group'];
        $candidates = [];

        foreach ($suffixes as $suffix) {
            $candidates[] = "{$cleanBase}-{$suffix}";
        }

        for ($i = 1; $i <= 20; $i++) {
            $candidates[] = "{$cleanBase}-{$i}";
        }

        $candidates = array_values(array_filter($candidates, fn ($slug) => ! in_array($slug, static::RESERVED_SLUGS, true)));

        $query = Tenant::whereIn('slug', $candidates);
        if ($ignoreTenantId !== null) {
            $query->where('id', '!=', $ignoreTenantId);
        }
        $existing = $query->pluck('slug')->all();

        $available = [];
        foreach ($candidates as $candidate) {
            if (! in_array($candidate, $existing, true)) {
                $available[] = $candidate;
                if (count($available) >= $count) {
                    break;
                }
            }
        }

        while (count($available) < $count) {
            $rand = rand(10, 99);
            $candidate = "{$cleanBase}-{$rand}";
            if (! in_array($candidate, static::RESERVED_SLUGS, true)) {
                $exists = Tenant::where('slug', $candidate)
                    ->when($ignoreTenantId !== null, fn ($q) => $q->where('id', '!=', $ignoreTenantId))
                    ->exists();
                if (! $exists && ! in_array($candidate, $available, true)) {
                    $available[] = $candidate;
                }
            }
        }

        return array_slice($available, 0, $count);
    }

    /**
     * Override edition mode for test simulation.
     */
    public static function fake(?bool $isCloud): void
    {
        static::$forceCloud = $isCloud;
    }
}
