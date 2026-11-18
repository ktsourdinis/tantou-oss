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

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\Edition;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CaddyDomainCheckController
{
    /**
     * Verify whether an incoming domain is authorized for Caddy On-Demand TLS certificate issuance.
     */
    public function __invoke(Request $request): Response
    {
        if (! Edition::customDomainsEnabled()) {
            return response('Not Found', 404);
        }

        $domain = strtolower(trim((string) $request->query('domain', '')));
        $domain = rtrim(explode(':', $domain)[0], '.');

        if ($domain === '' || in_array($domain, ['localhost', '127.0.0.1'], true)) {
            return response('Not Found', 404);
        }

        $appUrlHost = strtolower((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?? ''));
        if ($appUrlHost !== '' && $domain === $appUrlHost) {
            return response('Not Found', 404);
        }

        $tenant = Tenant::where('custom_domain', $domain)->first();

        if (
            ! $tenant
            || $tenant->slug === 'hellenic-demo-ops'
            || ! $tenant->hasActiveSubscription()
            || ! $tenant->canUseFeature('custom_domain')
        ) {
            return response('Not Found', 404);
        }

        return response('OK', 200);
    }
}
