<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the JSON /customer/* endpoints. Deliberately separate from Laravel's built-in
 * `auth` middleware alias: bootstrap/app.php's redirectGuestsTo() sends every guest
 * hitting `auth` middleware to the agent/company portal login, which would be wrong here
 * — these endpoints are consumed by the Vue dashboard (Profile.vue) via axios, so a plain
 * 401 JSON response (handled client-side) is what's actually needed, not a redirect.
 */
class EnsureCustomerAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('web')->check()) {
            abort(401, 'Please log in to continue.');
        }

        return $next($request);
    }
}
