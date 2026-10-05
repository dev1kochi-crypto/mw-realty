<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Keeps a portal user on the 2FA set-up page when:
 *  - their company makes 2FA mandatory (PortalUser::twoFactorRequired()) and it isn't set up, or
 *  - it's a new account that hasn't set it up or chosen "Skip for now" yet (needsTwoFactorPrompt()).
 */
class EnsurePortalTwoFactor
{
    private const ALLOWED_ROUTES = [
        'portal.two-factor.setup',
        'portal.two-factor.confirm',
        'portal.two-factor.skip',
        'portal.logout',
    ];

    public function handle(Request $request, Closure $next)
    {
        $portalUser = Auth::guard('portal')->user();

        if (!$portalUser || $portalUser->hasTwoFactorEnabled() || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        $required = $portalUser->twoFactorRequired();
        if (!$required && !$portalUser->needsTwoFactorPrompt()) {
            return $next($request);
        }

        $setupUrl = route('portal.two-factor.setup', $required ? [] : ['onboarding' => 1]);
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $required
                    ? 'Your company requires two-factor authentication. Please set it up to continue.'
                    : 'Please set up two-factor authentication or skip it to continue.',
                'redirect' => $setupUrl,
            ], 403);
        }

        // The set-up page itself explains what to do.
        return redirect()->to($setupUrl);
    }
}
