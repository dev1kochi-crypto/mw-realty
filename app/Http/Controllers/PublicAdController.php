<?php

namespace App\Http\Controllers;

use App\Models\AdStat;
use App\Models\CmsKit\Ad;
use App\Services\Visitors\VisitorTracker;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public ad endpoints: the ads running on a page (MW Realty's own, plus clients' approved ads in
 * their booked hours), and the impression / click counters behind CRM › Advertisements' reports.
 *
 * @group Advertisements
 */
class PublicAdController extends Controller
{
    /** Seconds each slide stays before the slider moves on. */
    private const SLIDE_SECONDS = 6;

    /**
     * Ad banner
     *
     * One active ad for a screen (picked at random when several run), or `false` when there is
     * none (hide the slot). Kept for older clients — the website's ad slots are sliders that show
     * every running ad: use **Ad slider** (`/api/ads/slides`).
     *
     * @queryParam placement string home, property-details, … Example: home
     * @queryParam lang string Example: en
     */
    public function index(Request $request)
    {
        $placement = (string) $request->input('placement', 'home');
        $lang = (string) $request->input('lang', app()->getLocale());

        // Explicit `false`, not `null` — Symfony's JsonResponse turns a `null` body into
        // `{}` (an empty object), which is truthy in JS and would defeat a `v-if` check.
        return response()->json(Ad::payloadFor($placement, $lang) ?? false);
    }

    /**
     * Ad slider
     *
     * Every ad running on a screen right now, in slide order (sponsored client ads first, in a
     * different order each time). Show them as a slider that moves on every `interval` seconds;
     * an empty list means hide the slot. Report each slide once it has been on screen
     * (**Ad impressions**) and taps on it (**Ad click**).
     *
     * @queryParam placement string home, property-details, … Example: home
     * @queryParam lang string Example: en
     *
     * @response 200 {"slides": [{"id": 12, "name": "Marina launch", "image_url": "https://…", "mobile_image_url": null, "image_alt": null, "link_url": "/property-details/marina-heights", "eyebrow": "New launch", "title": "Marina Heights", "text": "1–3 bed apartments from AED 1.2M", "sponsor": "Prime Homes"}], "interval": 6}
     */
    public function slides(Request $request)
    {
        $placement = (string) $request->input('placement', 'home');
        $lang = (string) $request->input('lang', app()->getLocale());

        return response()->json([
            'slides' => Ad::slidesFor($placement, $lang),
            'interval' => self::SLIDE_SECONDS,
        ]);
    }

    /**
     * Ad impressions
     *
     * Slides that were on screen (at least half visible for a second). Send each slide once per
     * screen visit — not again every time the slider comes back round to it.
     *
     * @bodyParam ids integer[] required Up to 30 ad ids. Example: [12, 15]
     *
     * @response 204 scenario="Recorded" {}
     */
    public function impressions(Request $request, VisitorTracker $tracker)
    {
        $data = $request->validate([
            'ids' => 'required|array|max:30',
            'ids.*' => 'integer',
        ]);

        if (!$tracker->isBot($request)) {
            AdStat::bump(Ad::whereKey($data['ids'])->currentlyRunning()->pluck('id')->all(), AdStat::IMPRESSIONS);
        }

        return response()->noContent();
    }

    /**
     * Ad click
     *
     * The user tapped an ad (send it before opening its link).
     *
     * @bodyParam id integer required Example: 12
     *
     * @response 204 scenario="Recorded" {}
     */
    public function click(Request $request, VisitorTracker $tracker)
    {
        $data = $request->validate(['id' => 'required|integer']);

        // A click can land just after the ad's last hour ended, so it only has to exist and be approved.
        $ad = Ad::whereKey($data['id'])->where('review_status', Ad::APPROVED)->first(['id']);
        if ($ad && !$tracker->isBot($request)) {
            AdStat::bump([$ad->id], AdStat::CLICKS);
        }

        return response()->noContent();
    }
}
