<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyDailyStat;
use App\Models\Visitors\VisitorEvent;
use App\Services\Visitors\VisitorTracker;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * The public site's page tracker (resources/js/composables/useVisitorTracking.js): one event per
 * SPA route visit, then the seconds the page was actually visible, sent as a beacon on leave.
 * Property detail pages become property views (and route an identified visitor to the listing's
 * agency / agent); listing pages with filters become searches.
 *
 * @group Tracking
 */
class VisitorTrackingController extends Controller
{
    /** Listing pages whose query string is a search. */
    private const SEARCH_PAGES = '#^/(properties|commercial|premium-properties|marketing-properties)(/map)?/?$#';
    /** Longest believable single page visit — a forgotten tab shouldn't count as hours of interest. */
    private const MAX_SECONDS = 4 * 3600;
    /** Contact buttons on a listing that count as a "lead click". */
    private const CONTACT_KINDS = [
        'call' => 'Call', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'enquiry' => 'Started an enquiry',
        'viewing' => 'Book a viewing', 'brochure' => 'Brochure', 'floor_plan' => 'Floor plan',
    ];

    public function __construct(private readonly VisitorTracker $tracker)
    {
    }

    /**
     * Screen view
     *
     * Call when a screen opens. Send the **website path** the screen corresponds to — that is
     * how views are classified: `/property-details/{slug}` = property view (counts towards the
     * listing's stats and routes the visitor to its agent), `/properties?bedrooms=2&...` = a
     * search, anything else = a page view. Keep the returned `id` to report time spent.
     *
     * Requires the `X-Device-Id` header (nothing is recorded without it).
     *
     * @bodyParam path string required Example: /property-details/virella-2
     * @bodyParam title string Screen title. Example: Virella 2
     * @bodyParam referrer string No-example
     *
     * @response 200 {"id": 5821}
     */
    public function page(Request $request)
    {
        $data = $request->validate([
            'path' => 'required|string|max:1000',
            'title' => 'nullable|string|max:255',
            'referrer' => 'nullable|string|max:1000',
        ]);

        $parts = parse_url($data['path']);
        $path = '/' . ltrim((string) ($parts['path'] ?? '/'), '/');
        parse_str((string) ($parts['query'] ?? ''), $query);
        $query = array_filter(array_slice($query, 0, 25), fn ($v) => is_scalar($v) && $v !== '');

        $attributes = [
            'url' => $data['path'],
            'title' => $data['title'] ?? null,
            'meta' => array_filter(['referrer' => $data['referrer'] ?? null]),
        ];
        $type = VisitorEvent::PAGE_VIEW;

        if (preg_match('#^/property-details/([^/]+)/?$#', $path, $match)
            && ($property = Property::where('status', true)->where('slug', urldecode($match[1]))->first(['id', 'slug', 'translations', 'reference_no']))) {
            $type = VisitorEvent::PROPERTY_VIEW;
            $attributes['property_id'] = $property->id;
            $attributes['title'] = $property->getTranslation('title') ?: $property->reference_no;
        } elseif (preg_match(self::SEARCH_PAGES, $path) && $query) {
            $type = VisitorEvent::SEARCH;
            $attributes['meta']['filters'] = array_map(fn ($v) => mb_substr((string) $v, 0, 100), $query);
        }

        $event = $this->tracker->record($request, $type, $attributes);

        // Listing performance: a "listing click" (bots never get here with an event).
        if ($event && $type === VisitorEvent::PROPERTY_VIEW) {
            PropertyDailyStat::bump([$event->property_id], PropertyDailyStat::CLICKS);
        }

        return response()->json(['id' => $event?->id]);
    }

    /**
     * Listing impressions
     *
     * Property cards that were visible on screen. Batch them, and send each id at most once per
     * day per device.
     *
     * Listing cards that were on screen (ids, de-duplicated per day by the browser).
     *
     * @bodyParam ids integer[] required Up to 60 property ids. Example: [111, 112, 150651]
     *
     * @response 204 scenario="Recorded" {}
     */
    public function impressions(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|max:60',
            'ids.*' => 'integer',
        ]);

        if (!$this->tracker->isBot($request)) {
            PropertyDailyStat::bump(Property::whereKey($data['ids'])->where('status', true)->pluck('id')->all(), PropertyDailyStat::IMPRESSIONS);
        }

        return response()->noContent();
    }

    /**
     * Contact click
     *
     * The user tapped a contact button on a listing (before the call/WhatsApp/email app opens).
     *
     * @bodyParam property_id integer required Example: 111
     * @bodyParam kind string required call, whatsapp, email, enquiry, viewing, brochure or floor_plan. Example: whatsapp
     *
     * @response 204 scenario="Recorded" {}
     */
    public function leadClick(Request $request)
    {
        $data = $request->validate([
            'property_id' => 'required|integer',
            'kind' => 'required|in:' . implode(',', array_keys(self::CONTACT_KINDS)),
        ]);

        $property = Property::where('status', true)->find($data['property_id']);
        if ($property && !$this->tracker->isBot($request)) {
            PropertyDailyStat::bump([$property->id], PropertyDailyStat::LEAD_CLICKS);
            $this->tracker->record($request, VisitorEvent::CONTACT_CLICK, [
                'property_id' => $property->id,
                'title' => self::CONTACT_KINDS[$data['kind']],
                'url' => $request->header('referer'),
                'meta' => ['kind' => $data['kind']],
            ]);
        }

        return response()->noContent();
    }

    /**
     * Time on screen
     *
     * Seconds the screen has been visible so far. Send whenever the screen is left or the app
     * goes to the background; the largest value wins, so re-sending is safe.
     *
     * @urlParam event integer required The `id` returned by the screen view call. Example: 5821
     * @bodyParam seconds integer required Example: 42
     *
     * @response 204 scenario="Recorded" {}
     */
    public function time(Request $request, int $event)
    {
        $seconds = min(max($request->integer('seconds'), 0), self::MAX_SECONDS);
        $browser = $this->tracker->browser($request);

        if ($browser && $seconds > 0) {
            VisitorEvent::whereKey($event)
                ->where('visitor_browser_id', $browser->id)
                ->where('duration_seconds', '<', $seconds)
                ->update(['duration_seconds' => $seconds]);
        }

        return response()->noContent();
    }
}
