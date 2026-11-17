<?php

namespace App\Http\Middleware;

use App\Support\Edition;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperadmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Edition::isCloud()) {
            abort(404, 'Platform Superadmin is only available in Cloud edition.');
        }

        abort_unless(auth()->check() && auth()->user()->is_superadmin, 403, 'Unauthorized. Superadmin platform privileges required.');

        return $next($request);
    }
}
