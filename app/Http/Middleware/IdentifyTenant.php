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

namespace App\Http\Middleware;

use App\Models\PostIt;
use App\Models\Tenant;
use App\Support\Edition;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Reserved subdomains that do not map to tenants.
     *
     * @var list<string>
     */
    public const array RESERVED_SUBDOMAINS = [
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
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Cookie & Session Safety on IPs
        if (Edition::isIpAddress($request->getHost())) {
            config(['session.domain' => null]);
        }

        // Avoid running multiple times per request lifecycle
        if ($request->attributes->get('tantou_tenant_identified')) {
            return $next($request);
        }
        $request->attributes->set('tantou_tenant_identified', true);

        // 2. Tenant Resolution
        // Explicit Public Drop-Box Route Path-based Resolution (/submit/{tenantSlug})
        if ($request->is('submit/*')) {
            $routeSlug = strtolower(trim((string) $request->segment(2)));
            if ($routeSlug !== '') {
                $tenant = Tenant::where('slug', $routeSlug)->first();
                if ($tenant) {
                    app()->instance('currentTenant', $tenant);

                    return $next($request);
                }
            }
        }

        // Explicit Public Tracker Route Path-based Resolution (/track/{token})
        if ($request->is('track/*')) {
            $trackingToken = trim((string) $request->segment(2));
            if ($trackingToken !== '') {
                $trackedPostIt = PostIt::withoutGlobalScopes()->where('tracking_token', $trackingToken)->first();
                if ($trackedPostIt && $trackedPostIt->tenant) {
                    app()->instance('currentTenant', $trackedPostIt->tenant);

                    return $next($request);
                }
            }
        }

        // 3. Custom Domain Resolution (for Business Tier tenants)
        $host = strtolower($request->getHost());
        $customDomainTenant = Tenant::where('custom_domain', $host)->first();
        if ($customDomainTenant && $customDomainTenant->canUseFeature('custom_domain')) {
            app()->instance('currentTenant', $customDomainTenant);

            if ($request->is('register')) {
                return redirect()->to(route('login'));
            }

            if ($request->is('cloud/autologin/*')) {
                return $next($request);
            }

            $user = $request->user() ?? (Auth::check() ? Auth::user() : null);
            if ($user && (int) $user->tenant_id !== (int) $customDomainTenant->id) {
                if ($user->tenant) {
                    $signedUrl = Edition::signedTenantAutoLoginUrl($user, $request);

                    return redirect()->away($signedUrl);
                }

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->to(route('login'))
                    ->with('error', __('You do not have access to this workspace.'));
            }

            return $next($request);
        }

        if (Edition::usesSubdomains($request)) {
            $host = $request->getHost();
            $baseDomain = Edition::baseDomain();

            $subdomainSuffix = '.'.$baseDomain;
            $isSubdomain = false;
            $subdomain = null;

            if ($host !== $baseDomain && str_ends_with($host, $subdomainSuffix)) {
                $extracted = Str::beforeLast($host, $subdomainSuffix);
                if (! in_array(strtolower($extracted), self::RESERVED_SUBDOMAINS, true)) {
                    $isSubdomain = true;
                    $subdomain = strtolower($extracted);
                }
            }

            if ($isSubdomain && $subdomain !== null) {
                // Resolve tenant on subdomain or abort 404
                $tenant = Tenant::where('slug', $subdomain)->firstOrFail();
                app()->instance('currentTenant', $tenant);

                // Subdomain cannot register new tenants -> redirect to central register
                if ($request->is('register')) {
                    $port = $request->getPort();
                    $portString = ($port && ! in_array($port, [80, 443], true)) ? ":{$port}" : '';
                    $scheme = $request->getScheme();

                    return redirect()->to("{$scheme}://{$baseDomain}{$portString}/register");
                }

                // Allow autologin endpoint to process without interference
                if ($request->is('cloud/autologin/*')) {
                    return $next($request);
                }

                // Authenticated user belongs to a different tenant -> redirect to own workspace or logout
                $user = $request->user() ?? (Auth::check() ? Auth::user() : null);
                if ($user && (int) $user->tenant_id !== (int) $tenant->id) {
                    if ($user->tenant) {
                        $signedUrl = Edition::signedTenantAutoLoginUrl($user, $request);

                        return redirect()->away($signedUrl);
                    }

                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->to(Edition::tenantUrl($tenant, '/login', $request))
                        ->with('error', __('You do not have access to this workspace.'));
                }
            } elseif ($host === $baseDomain) {
                // Central Domain: Authenticated user visiting board, team, login redirects to tenant subdomain
                $user = $request->user() ?? (Auth::check() ? Auth::user() : null);
                if ($user && $user->tenant && ($request->is('board', 'board/*', 'team', 'team/*', 'login'))) {
                    $signedSubdomainUrl = Edition::signedTenantAutoLoginUrl($user, $request);

                    return redirect()->away($signedSubdomainUrl);
                }
            }
        } else {
            // Non-subdomain / IP / Path / Single mode:
            // Priority 1: Query parameter ?workspace={slug}
            $workspaceSlug = $request->query('workspace');
            $tenant = null;

            if ($workspaceSlug) {
                $tenant = Tenant::where('slug', $workspaceSlug)->first();
                if ($tenant) {
                    session(['active_tenant_id' => $tenant->id]);
                }
            }

            // Priority 2: Authenticated user's tenant
            if (! $tenant) {
                $user = $request->user() ?? (Auth::check() ? Auth::user() : null);
                if ($user && $user->tenant) {
                    $tenant = $user->tenant;
                    session(['active_tenant_id' => $tenant->id]);
                }
            }

            // Priority 3: Session active_tenant_id
            if (! $tenant && session()->has('active_tenant_id')) {
                $tenant = Tenant::find(session('active_tenant_id'));
            }

            // Priority 4: Default fallback Hellenic Demo Ops or first tenant
            if (! $tenant) {
                $tenant = Tenant::where('slug', 'hellenic-demo-ops')->first() ?? Tenant::first();
            }

            if ($tenant) {
                app()->instance('currentTenant', $tenant);
            }
        }

        return $next($request);
    }
}
