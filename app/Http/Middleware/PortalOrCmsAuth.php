<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The Properties and CRM dashboards are shared by two audiences: Super Admin
 * (global view, logged in via the "cms" guard) and Agents/Companies (scoped
 * to their own data, logged in via the "portal" guard). Either session is
 * enough to get in; the controllers decide what "own data" means per guard.
 */
class PortalOrCmsAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::guard('portal')->check()) {
            return $next($request);
        }

        // Global (unscoped) access is for Super Admin specifically, not every
        // CMS-panel user — this app also has a lower-privilege "Client" role.
        $cmsUser = Auth::guard('cms')->user();
        if ($cmsUser && $cmsUser->hasRole('superadmin')) {
            return $next($request);
        }

        return redirect()->route('portal.login');
    }
}
