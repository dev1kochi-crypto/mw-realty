<?php

namespace App\Http\Controllers\Crm;

use App\Models\HelpGuide;
use App\Support\PortalHelp;
use Illuminate\Routing\Controller;

/**
 * @group CRM Auth
 */
class HelpController extends Controller
{
    /**
     * Help desk
     *
     * Every active help guide, A–Z, with its full content — the CRM's Help Desk page lists them and
     * opens one as the same slide tour as the "!" icon.
     *
     * @response 200 {"data": [{"topic": "leads", "title": "Leads", "icon": "fa-address-book", "intro": "…", "features": [], "steps": [], "tips": []}]}
     */
    public function index()
    {
        return response()->json([
            'data' => HelpGuide::active()->orderBy('title')
                // Credits switched off and none left → no credits guide.
                ->when(!\App\Services\CreditService::shownTo(app(\App\Services\Crm\OwnerContext::class)->owner()), fn ($q) => $q->where('topic', '!=', 'credits'))
                ->get()
                ->map(fn (HelpGuide $guide) => ['topic' => $guide->topic, ...$guide->toGuide()]),
        ]);
    }

    /**
     * Help guide
     *
     * A module's "how it works" guide (the "!" icon): title, intro, features, steps and tips —
     * content managed in Admin › Help Desk (help_guides).
     *
     * @urlParam topic string required Example: stages
     *
     * @response 200 {"title": "Stage", "icon": "fa-layer-group", "intro": "…", "features": [], "steps": [], "tips": []}
     */
    public function __invoke(string $topic)
    {
        $guide = PortalHelp::load($topic);
        abort_unless($guide, 404);
        abort_if($topic === 'credits' && !\App\Services\CreditService::shownTo(app(\App\Services\Crm\OwnerContext::class)->owner()), 404);

        return response()->json($guide);
    }

    /**
     * Help guides seen
     *
     * Call after the first guide opened by itself (`help_auto_open` on `/auth/me`): no guide
     * opens by itself again, on any screen. Agents / companies only.
     *
     * @response 200 {"success": true}
     */
    public function seen()
    {
        $user = \Illuminate\Support\Facades\Auth::guard('portal')->user();
        if ($user && !in_array(PortalHelp::DISMISSED, $user->seen_help_topics ?? [], true)) {
            $user->forceFill(['seen_help_topics' => [...($user->seen_help_topics ?? []), PortalHelp::DISMISSED]])->save();
        }

        return response()->json(['success' => true]);
    }
}
