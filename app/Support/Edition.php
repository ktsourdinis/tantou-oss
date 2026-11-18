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

        $configEdition = config('tantou.edition');
        if ($configEdition === 'cloud') {
            return true;
        }
        if ($configEdition === 'community') {
            return false;
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
     * Determine if the application is running in Community (open-source self-hosted) edition.
     */
    public static function isCommunity(): bool
    {
        return ! static::isCloud();
    }

    /**
     * Determine if Cloud commercial marketing & pricing should be hidden.
     */
    public static function hideMarketing(): bool
    {
        return (bool) config('tantou.hide_marketing', false);
    }

    /**
     * Resolve the effective routing mode.
     * When running community edition, defaults to 'single' (single-tenant operation).
     */
    public static function routingMode(): string
    {
        $mode = config('tantou.routing_mode', 'auto');
        if (($mode === 'auto' || empty($mode)) && static::isCommunity()) {
            return 'single';
        }

        return $mode ?: 'single';
    }

    /**
     * Determine if new tenant organizations can be registered.
     */
    public static function canRegisterNewTenant(): bool
    {
        return static::isCloud() || Tenant::count() === 0;
    }

    /**
     * Helper checking if multi-tenant workspace registration is enabled.
     */
    public static function allowsTenantRegistration(): bool
    {
        return static::canRegisterNewTenant();
    }

    /**
     * Resolve the base domain for the application.
     */
    public static function baseDomain(): string
    {
        if (config()->has('tantou.base_domain') && config('tantou.base_domain') !== env('TANTOU_BASE_DOMAIN', 'localhost')) {
            $domain = (string) config('tantou.base_domain');
        } elseif (config('app.base_domain') !== null) {
            $domain = (string) config('app.base_domain');
        } else {
            $domain = (string) (config('tantou.base_domain') ?? env('TANTOU_BASE_DOMAIN', 'localhost'));
        }

        if (str_contains($domain, '://')) {
            $parsed = parse_url($domain, PHP_URL_HOST);
            if ($parsed) {
                return $parsed;
            }
        }

        return trim(explode(':', $domain)[0]);
    }

    /**
     * Determine if a hostname is an IP address or intranet host that cannot support subdomains.
     */
    public static function isIpAddress(?string $host = null): bool
    {
        $host = $host ?? (request() ? request()->getHost() : parse_url((string) config('app.url'), PHP_URL_HOST));

        if (! $host) {
            return false;
        }

        $cleanHost = strtolower(trim(explode(':', $host)[0]));

        if (filter_var($cleanHost, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        if ($cleanHost === '127.0.0.1') {
            return true;
        }

        if ($cleanHost === 'localhost') {
            // When base_domain is configured as localhost in Cloud mode (with subdomains enabled),
            // then *.localhost are treated as valid subdomains under the localhost base domain.
            if (static::baseDomain() === 'localhost' && static::isCloud() && ! in_array(config('tantou.routing_mode', 'auto'), ['path', 'single'], true)) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Determine if subdomains are active for this request or environment.
     */
    public static function usesSubdomains(?Request $request = null): bool
    {
        $routingMode = static::routingMode();

        if ($routingMode === 'subdomain') {
            return true;
        }

        if ($routingMode === 'path' || $routingMode === 'single') {
            return false;
        }

        // 'auto' mode
        $request = $request ?? (request() ?: null);
        $host = $request ? $request->getHost() : parse_url((string) config('app.url'), PHP_URL_HOST);

        return static::isCloud() && ! static::isIpAddress($host);
    }

    /**
     * Construct the full URL for a tenant's workspace, dynamically switching
     * between subdomain URL ({slug}.baseDomain) and IP/path URL ({ip}/path?workspace={slug}).
     */
    public static function workspaceUrl(Tenant $tenant, string $path = '/board', ?Request $request = null): string
    {
        $request = $request ?? request();
        $scheme = $request ? $request->getScheme() : (str_starts_with((string) config('app.url'), 'https://') ? 'https' : 'http');
        $port = $request ? $request->getPort() : parse_url((string) config('app.url'), PHP_URL_PORT);
        $portString = ($port && ! in_array((int) $port, [80, 443], true)) ? ":{$port}" : '';
        $pathStr = $path !== '' ? ('/'.ltrim($path, '/')) : '';

        if (! empty($tenant->custom_domain) && $tenant->canUseFeature('custom_domain')) {
            return "{$scheme}://{$tenant->custom_domain}{$portString}{$pathStr}";
        }

        if (static::usesSubdomains($request)) {
            $baseDomain = static::baseDomain();

            return "{$scheme}://{$tenant->slug}.{$baseDomain}{$portString}{$pathStr}";
        }

        $host = $request ? $request->getHost() : (parse_url((string) config('app.url'), PHP_URL_HOST) ?? 'localhost');

        if (static::routingMode() === 'single' || static::isSelfHosted()) {
            return "{$scheme}://{$host}{$portString}{$pathStr}";
        }

        $delimiter = str_contains($pathStr, '?') ? '&' : '?';

        return "{$scheme}://{$host}{$portString}{$pathStr}{$delimiter}workspace={$tenant->slug}";
    }

    /**
     * Construct the full URL for a tenant's subdomain, preserving scheme and port from the request.
     */
    public static function tenantUrl(Tenant $tenant, string $path = '/board', ?Request $request = null): string
    {
        $request = $request ?? request();
        $scheme = $request ? $request->getScheme() : 'http';
        $port = $request ? $request->getPort() : 80;
        $portString = ($port && ! in_array($port, [80, 443], true)) ? ":{$port}" : '';
        $pathStr = $path !== '' ? ('/'.ltrim($path, '/')) : '';

        if (! empty($tenant->custom_domain) && $tenant->canUseFeature('custom_domain')) {
            return "{$scheme}://{$tenant->custom_domain}{$portString}{$pathStr}";
        }

        $baseDomain = static::baseDomain();

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
