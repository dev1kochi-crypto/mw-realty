<?php

namespace App\Http\Middleware;

use App\Models\Plan;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * `plan.feature:lead_insights` (or several keys: any one unlocks the route) — sections a plan must
 * include (plans.plan_features, see Plan::GATED_FEATURES). Answers 403 with `plan_locked`, which the
 * CRM pages turn into the "Unlock …" card (PlanLockedCard.vue). Super Admin (no portal login) is
 * never blocked.
 */
class EnsurePlanIncludesFeature
{
    public function handle(Request $request, Closure $next, string ...$features)
    {
        $user = Auth::guard('portal')->user();

        if ($user && !collect($features)->contains(fn ($f) => $user->planAllows($f))) {
            $label = collect($features)->map(fn ($f) => Plan::GATED_FEATURES[$f] ?? $f)->implode(' / ');

            return response()->json([
                'message' => "{$label} is included in paid plans. Upgrade your plan to use it.",
                'plan_locked' => [
                    'features' => $features,
                    'plan' => $user->effectivePlan()?->getTranslation('name'),
                    'agency' => $user->isOnAgencyPlan() ? $user->company?->displayName() : null,
                ],
            ], 403);
        }

        return $next($request);
    }
}
