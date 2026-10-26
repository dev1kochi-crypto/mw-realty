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
        'crm.two-factor',
        'portal.two-factor.setup',
        'portal.logout',
    ];

    public function handle(Request $request, Closure $next)
    {
        $portalUser = Auth::guard('portal')->user();

        if (!$portalUser || $portalUser->hasTwoFactorEnabled() || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        if (!$portalUser->mustSetUpTwoFactor()) {
            return $next($request);
        }
        $required = $portalUser->twoFactorRequired();

        // The CRM app's set-up screen; its API (/api/crm/account/two-factor/*) sits outside this check.
        $setupUrl = route('crm.two-factor', $required ? [] : ['onboarding' => 1]);
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $required
                    ? ($portalUser->twoFactorRequiredByPlatform()
                        ? 'Two-factor authentication is required on every MW Realty account. Please set it up to continue.'
                        : 'Your company requires two-factor authentication. Please set it up to continue.')
                    : 'Please set up two-factor authentication or skip it to continue.',
                'redirect' => $setupUrl,
            ], 403);
        }

        // The set-up page itself explains what to do.
        return redirect()->to($setupUrl);
    }
}
