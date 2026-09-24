<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Real (server-side) enforcement of the CRM's "approved accounts only" gate — the
 * sidebar's padlock icons (resources/views/portal/layouts/app.blade.php) are purely
 * cosmetic and were never backed by anything checking on the request itself.
 *
 * A pending/rejected Agent or Company can still reach their dashboard and profile
 * (those routes deliberately do NOT get this middleware, so completing/fixing KYC
 * documents is never blocked) but is redirected away from Leads/Reports/Master and
 * from browsing/editing already-created Properties until Super Admin approves them.
 */
class EnsurePortalAccountApproved
{
    /**
     * Mirrors PortalPropertyController::isAdmin() exactly: a portal-guard login
     * always wins even if a superadmin cms-guard session is also active in the same
     * browser, so a Super Admin browsing the portal (no portal-guard session) is
     * never blocked by this middleware.
     */
    protected function isAdmin(): bool
    {
        return !Auth::guard('portal')->check() && (bool) Auth::guard('cms')->user()?->hasRole('superadmin');
    }

    public function handle(Request $request, Closure $next)
    {
        if ($this->isAdmin()) {
            return $next($request);
        }

        $user = Auth::guard('portal')->user();

        if ($user && $user->status !== 'approved') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your account is pending Super Admin approval.'], 403);
            }

            return redirect()->route('portal.dashboard')
                ->with('error', 'Your account is pending Super Admin approval — complete your KYC documents to speed up review.');
        }

        return $next($request);
    }
}
