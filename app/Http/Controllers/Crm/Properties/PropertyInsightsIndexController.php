<?php

namespace App\Http\Controllers\Crm\Properties;

use App\Http\Controllers\Crm\Concerns\ScopesListings;
use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertyDailyStat;
use App\Services\ListingQualityService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * @group CRM Property Insights
 *
 * How every listing performs on the website over a period — impressions, listing clicks, lead
 * clicks, leads and the quality score — so weak listings stand out. Same listings as the
 * Properties / Commercial menus (Super Admin: every listing). A row opens the listing's own
 * performance panel (PropertyInsightsController). Plan feature `property_insights`.
 */
class PropertyInsightsIndexController extends Controller
{
    use ScopesListings;

    public const PERIODS = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'];

    public const SORTS = [
        'impressions' => 'Most impressions',
        'clicks' => 'Most clicks',
        'leads' => 'Most leads',
        'no_views' => 'No impressions first',
        'newest' => 'Newest first',
    ];

    public const SEGMENTS = ['' => 'All listings', Property::SEGMENT_RESIDENTIAL => 'Residential', Property::SEGMENT_COMMERCIAL => 'Commercial'];

    /**
     * Property insights
     *
     * 20 listings per page with their numbers for the period, plus totals across all of them and a
     * day-by-day series of impressions / clicks.
     *
     * @queryParam days integer 7, 30 or 90. Example: 30
     * @queryParam sort string impressions, clicks, leads, no_views or newest. Example: impressions
     * @queryParam segment string residential or commercial (empty = both). Example: residential
     * @queryParam search string Title, reference, address or permit number. Example: marina
     * @queryParam page integer Example: 1
     */
    public function __invoke(Request $request, ListingQualityService $quality)
    {
        $days = (int) $request->query('days');
        $days = array_key_exists($days, self::PERIODS) ? $days : 30;
        $sort = array_key_exists((string) $request->query('sort'), self::SORTS) ? (string) $request->query('sort') : 'impressions';
        $segment = array_key_exists((string) $request->query('segment'), self::SEGMENTS) ? (string) $request->query('segment') : '';
        $search = mb_substr(trim((string) $request->query('search', '')), 0, 100);
        $from = today()->subDays($days - 1);

        // Every listing this account sees in its menus — on the market only (sold ones have their own menu).
        $scope = fn () => Property::query()->available()
            ->when($this->ownerId(), fn ($q) => $q->accessibleBy($this->viewer()))
            ->when($segment === Property::SEGMENT_COMMERCIAL, fn ($q) => $q->segment($segment))
            ->when($segment === Property::SEGMENT_RESIDENTIAL, fn ($q) => $q->residential());

        $stats = PropertyDailyStat::where('date', '>=', $from)->groupBy('property_id')
            ->selectRaw('property_id, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(lead_clicks) as lead_clicks');

        $listings = $scope()
            ->leftJoinSub($stats, 'period_stats', 'period_stats.property_id', '=', 'properties.id')
            ->select('properties.*')
            ->selectRaw('COALESCE(period_stats.impressions, 0) as period_impressions, COALESCE(period_stats.clicks, 0) as period_clicks, COALESCE(period_stats.lead_clicks, 0) as period_lead_clicks')
            ->withCount(['leads as period_leads' => fn ($q) => $q->where('leads.created_at', '>=', $from)])
            ->with(['owner', 'agent:id,name,avatar', 'details', 'floorPlans'])
            ->when($search !== '', fn ($q) => $q->portalSearch($search, $this->isAdmin()))
            ->when($sort === 'impressions', fn ($q) => $q->orderByDesc('period_impressions'))
            ->when($sort === 'clicks', fn ($q) => $q->orderByDesc('period_clicks'))
            ->when($sort === 'leads', fn ($q) => $q->orderByDesc('period_leads'))
            ->when($sort === 'no_views', fn ($q) => $q->orderBy('period_impressions'))
            ->orderByDesc('properties.created_at')
            ->orderByDesc('properties.id')
            ->paginate(20);

        $ids = $scope()->select('properties.id');
        $totals = PropertyDailyStat::where('date', '>=', $from)->whereIn('property_id', $ids)
            ->selectRaw('COALESCE(SUM(impressions), 0) as impressions, COALESCE(SUM(clicks), 0) as clicks, COALESCE(SUM(lead_clicks), 0) as lead_clicks')
            ->first();
        $daily = PropertyDailyStat::where('date', '>=', $from)->whereIn('property_id', $ids)
            ->groupBy('date')->selectRaw('date, SUM(impressions) as impressions, SUM(clicks) as clicks')
            ->get()->keyBy(fn ($row) => $row->date->toDateString());

        return response()->json([
            'data' => collect($listings->items())->map(fn (Property $p) => $this->rowJson($p, $quality)),
            'meta' => [
                'current_page' => $listings->currentPage(),
                'last_page' => $listings->lastPage(),
                'per_page' => $listings->perPage(),
                'total' => $listings->total(),
                'from' => $listings->firstItem(),
                'to' => $listings->lastItem(),
            ],
            'totals' => [
                'listings' => $scope()->count(),
                'live' => $scope()->where('properties.status', true)->count(),
                'impressions' => (int) $totals->impressions,
                'clicks' => (int) $totals->clicks,
                'lead_clicks' => (int) $totals->lead_clicks,
                'leads' => Lead::whereIn('property_id', $ids)->where('created_at', '>=', $from)->count(),
                // Listings nobody saw on the website in the period.
                'no_views' => $scope()->whereNotIn('properties.id', PropertyDailyStat::where('date', '>=', $from)
                    ->where('impressions', '>', 0)->select('property_id'))->count(),
            ],
            'series' => collect(range(0, $days - 1))->map(function ($offset) use ($from, $daily) {
                $date = $from->copy()->addDays($offset)->toDateString();

                return ['date' => $date, 'impressions' => (int) ($daily[$date]->impressions ?? 0), 'clicks' => (int) ($daily[$date]->clicks ?? 0)];
            }),
            'days' => $days,
            'periods' => $this->options(self::PERIODS),
            'sorts' => $this->options(self::SORTS),
            'segments' => $this->options(self::SEGMENTS),
            'is_admin' => $this->isAdmin(),
        ]);
    }

    private function rowJson(Property $p, ListingQualityService $quality): array
    {
        $impressions = (int) $p->period_impressions;
        $clicks = (int) $p->period_clicks;
        $agent = $p->agent ?? ($p->owner?->type === 'agent' ? $p->owner : null);

        return [
            'id' => $p->id,
            'segment' => $p->segment,
            'title' => $p->getTranslation('title') ?: $p->reference_no,
            'thumb' => $p->galleryImages()[0]['url'] ?? null,
            'reference_no' => $p->reference_no,
            'status' => (bool) $p->status,
            'featured' => (bool) $p->featured,
            'agent' => $agent?->name,
            'owner' => $this->isAdmin() && $p->owner ? $p->owner->displayName() : null,
            'impressions' => $impressions,
            'clicks' => $clicks,
            'lead_clicks' => (int) $p->period_lead_clicks,
            'leads' => (int) $p->period_leads,
            'quality' => collect($quality->score($p))->only(['score', 'tone']),
        ];
    }

    private function options(array $options): array
    {
        return collect($options)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all();
    }
}
