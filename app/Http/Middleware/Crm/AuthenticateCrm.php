<?php

namespace App\Http\Middleware\Crm;

use App\Models\PortalUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * `crm.auth` — who is calling /api/crm/*:
 *  - the mobile app: an agent/company Bearer token (POST /api/crm/auth/login), or
 *  - the CRM web app (/crm): the browser's existing portal session, or a Super Admin's
 *    CMS session (global view) — started for it by Sanctum's stateful middleware.
 *
 * A token's account is put on the "portal" guard, exactly as a session login would be, so
 * everything below — OwnerContext, portal.approved / portal.2fa, the FormRequests and the
 * reused Portal controllers — scopes the data with no API-specific code of its own.
 */
class AuthenticateCrm
{
    public function handle(Request $request, Closure $next)
    {
        if ($plainToken = $request->bearerToken()) {
            $token = PersonalAccessToken::findToken($plainToken);
            $portalUser = $token?->tokenable;

            if (!$portalUser instanceof PortalUser || !$portalUser->is_active || $token->expires_at?->isPast()) {
                return $this->unauthenticated();
            }

            $token->forceFill(['last_used_at' => now()])->save();
            Auth::guard('portal')->setUser($portalUser->withAccessToken($token));
            Auth::shouldUse('portal');

            return $next($request);
        }

        // Session sign-ins only count when Sanctum started a session for the /crm web app.
        if (!$request->hasSession()) {
            return $this->unauthenticated();
        }

        if (Auth::guard('portal')->check()) {
            Auth::shouldUse('portal');

            return $next($request);
        }

        // Global (unscoped) access is for Super Admin specifically, like PortalOrCmsAuth.
        if (Auth::guard('cms')->user()?->hasRole('superadmin')) {
            Auth::shouldUse('cms');

            return $next($request);
        }

        return $this->unauthenticated();
    }

    private function unauthenticated()
    {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
