<?php

namespace App\Http\Middleware\Crm;

use Closure;
use Illuminate\Http\Request;

/**
 * Every /api/crm/* request is treated as `Accept: application/json`, even when the app
 * doesn't send it — the reused Portal controllers and middleware (portal.approved,
 * portal.2fa) pick their JSON answer over a redirect / Blade view from wantsJson().
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
