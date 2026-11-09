<?php

namespace App\Services\MarketDemand;

use App\Models\Property;
use App\Services\PropertyInsightService;
use App\Services\PropertyLabels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Area-level demand from the anonymous aggregate (market_demand_daily, filled by DemandAggregator)
 * against supply (live listings):
 *
 *   forSlug()   — Property Details › Buyer Demand: the listing's area and its segment
 *                 (same property type + bedrooms) — demand level, changes, price benchmark,
 *                 demand vs supply by bedrooms and price band, monthly trend, amenities, insights.
 *   forArea()   — CRM › Market Demand: the same report for any area, purpose and type.
 *
 * Interest score = searches + 3 × saved searches + views + 3 × favourites + 5 × enquiries
 * (config market_demand.weights). Demand level compares the segment's interest per live listing
 * with the same figure across its city (or the whole platform for a city-wide report).
 * Counts below config market_demand.min_count are returned as null ("fewer than N").
 */
class MarketDemandService
{
    private const METRICS = ['searches', 'saved_searches', 'views', 'favorites', 'enquiries'];

    public function __construct(
        private readonly PropertyInsightService $insights,
        private readonly PropertyLabels $labels,
    ) {
    }

    /** The Buyer Demand block for a published listing (null when it isn't one). */
    public function forSlug(string $slug): ?array
    {
        $property = Property::where('status', true)->where('slug', $slug)->first();
        if (!$property) {
            return null;
        }
        $version = Cache::get('market-demand:version', 0);

        return Cache::remember("market-demand:{$property->id}:{$property->updated_at?->timestamp}:{$version}", 3600, fn () => $this->forProperty($property));
    }

    public function forProperty(Property $property): array
    {
        $area = DemandSegments::area($property);
        $purpose = DemandSegments::purpose($property->listing_type) ?? 'sale';
        if (!$area['city'] && !$area['community']) {
            return ['available' => false];
        }

        $focus = [
            'type' => $property->property_type,
            'beds' => DemandSegments::bedrooms($property->bedrooms),
            'band' => DemandSegments::priceBand($purpose, DemandSegments::annualPrice($property)),
            'amenities' => DemandAggregator::amenityKeys($property->loadMissing('details')),
            'property_id' => $property->id,
        ];

        $report = $this->build($area['city'], $area['community'], $purpose, $property->property_type, $focus);
        $report['pricing'] = $this->pricing($property, $report['_supply'], $focus);
        $report['insights'] = $this->insightList($report, $focus);
        unset($report['_supply']);

        return $report;
    }

    /** CRM › Market Demand: one area (community, else city, else the whole platform). */
    public function forArea(?string $city, ?string $community, string $purpose, ?string $type = null): array
    {
        $version = Cache::get('market-demand:version', 0);
        $key = 'market-demand-area:' . md5(json_encode([$city, $community, $purpose, $type, $version]));

        return Cache::remember($key, 1800, function () use ($city, $community, $purpose, $type) {
            $report = $this->build($city, $community, $purpose, $type, null);
            $report['insights'] = $this->insightList($report, null);
            unset($report['_supply']);

            return $report;
        });
    }

    /** Areas that have demand or listings, for the CRM area picker: [{city, community, label, listings}]. */
    public function areas(string $term = '', int $page = 1, int $perPage = 20): array
    {
        $term = mb_strtolower(trim($term));
        $all = Cache::remember('market-demand-areas', 1800, function () {
            $rows = Property::where('status', true)->available()->get(['translations'])
                ->map(fn ($p) => DemandSegments::area($p))
                ->filter(fn ($a) => $a['city']);
            $cities = $rows->groupBy('city')->map(fn ($g, $city) => ['city' => $city, 'community' => null, 'label' => $city, 'listings' => $g->count()]);
            $communities = $rows->filter(fn ($a) => $a['community'])
                ->groupBy(fn ($a) => $a['city'] . '|' . $a['community'])
                ->map(fn ($g) => ['city' => $g[0]['city'], 'community' => $g[0]['community'], 'label' => $g[0]['community'] . ', ' . $g[0]['city'], 'listings' => $g->count()]);

            return $cities->sortByDesc('listings')->values()->concat($communities->sortByDesc('listings')->values())->all();
        });

        $matches = collect($all)->filter(fn ($a) => $term === '' || str_contains(mb_strtolower($a['label']), $term))->values();

        return [
            'data' => $matches->forPage($page, $perPage)->values()->all(),
            'next_page' => $matches->count() > $page * $perPage ? $page + 1 : null,
        ];
    }

    private function build(?string $city, ?string $community, string $purpose, ?string $type, ?array $focus): array
    {
        $c = config('market_demand');
        $days = (int) $c['window_days'];
        $curFrom = today()->subDays($days - 1);
        $prevFrom = $curFrom->copy()->subDays($days);
        $trendFrom = today()->startOfMonth()->subMonths((int) $c['trend_months'] - 1);

        $rows = DB::table('market_demand_daily')
            ->where('purpose', $purpose)
            ->where('date', '>=', ($prevFrom->lt($trendFrom) ? $prevFrom : $trendFrom)->toDateString())
            ->when($community, fn ($q) => $q->where('community', $community))
            ->when($city, fn ($q) => $q->where('city', $city))
            ->get()
            ->map(function ($r) {
                $r->date = Carbon::parse($r->date);

                return $r;
            });

        $supply = $this->supply($city, $community, $purpose);

        $inType = fn ($r) => !$type || $r->property_type === null || $r->property_type === $type;
        $typeRows = $rows->filter($inType);
        $typeSupply = $supply->filter(fn ($p) => !$type || $p->property_type === $type);

        $cur = fn (Collection $set) => $set->filter(fn ($r) => $r->date >= $curFrom);
        $prev = fn (Collection $set) => $set->filter(fn ($r) => $r->date >= $prevFrom && $r->date < $curFrom);

        // The area as a whole (every type), against its city (a community) or the platform (a city).
        $areaSummary = $this->summary($cur($rows), $prev($rows), $supply->count());
        $areaSummary['level'] = $community || $city
            ? $this->level($areaSummary, $this->baseline($community ? $city : null, $purpose, null, null, (bool) $community))
            : null;

        $segment = null;
        if ($focus) {
            $inSegment = fn ($r) => $inType($r) && ($focus['beds'] === null || $r->bedrooms === null || (int) $r->bedrooms === $focus['beds']);
            $segmentSupply = $typeSupply->filter(fn ($p) => $focus['beds'] === null || DemandSegments::bedrooms($p->bedrooms) === $focus['beds']);
            $segmentRows = $rows->filter($inSegment);
            $segment = $this->summary($cur($segmentRows), $prev($segmentRows), $segmentSupply->count());
            // Like for like: the same type + bedrooms across the whole city (or platform).
            $segment['level'] = $this->level($segment, $this->baseline($community ? $city : null, $purpose, $type, $focus['beds'], (bool) $community));
        }

        $shareRows = $typeRows->filter(fn ($r) => $r->date >= $prevFrom); // both windows — steadier shares

        return [
            'available' => true,
            'purpose' => $purpose,
            'area' => [
                'city' => $city,
                'community' => $community,
                'name' => $community ?: ($city ?: null),
                'scope' => $community ? 'community' : ($city ? 'city' : 'all'),
            ],
            'segment_info' => [
                'type' => $type,
                'type_label' => $type ? $this->typeLabel($type) : null,
                'beds' => $focus['beds'] ?? null,
                'band' => $focus['band'] ?? null,
            ],
            'window_days' => $days,
            'share_days' => $days * 2,
            'fewer_than' => (int) $c['min_count'],
            'max_bedrooms' => (int) $c['max_bedrooms'], // this bedroom value means "N or more"
            'area_demand' => $areaSummary,
            'segment' => $segment,
            'bedrooms' => $this->breakdown($shareRows, $typeSupply, 'bedrooms', fn ($p) => DemandSegments::bedrooms($p->bedrooms), $focus['beds'] ?? null, $curFrom),
            'price_bands' => $this->breakdown($shareRows, $typeSupply, 'price_band', fn ($p) => DemandSegments::priceBand($purpose, DemandSegments::annualPrice($p)), $focus['band'] ?? null, $curFrom, $purpose),
            'trend' => $this->trend($typeRows, $trendFrom),
            'amenities' => $this->amenities($city, $community, $purpose, $supply, $focus['amenities'] ?? []),
            'generated_at' => now()->toIso8601String(),
            '_supply' => $typeSupply,
        ];
    }

    /** Live listings in the area for this purpose (id, type, bedrooms, price, size, amenities). */
    private function supply(?string $city, ?string $community, string $purpose): Collection
    {
        return Property::where('status', true)->available()
            ->when($purpose === 'rent', fn ($q) => $q->where('listing_type', 'rent'), fn ($q) => $q->where('listing_type', '!=', 'rent'))
            ->when($community, fn ($q) => $q->where('translations->en->community', $community))
            ->when($city, fn ($q) => $q->where('translations->en->city', $city))
            ->with('details')
            ->get(['id', 'property_type', 'bedrooms', 'price', 'rental_period', 'listing_type', 'sqft']);
    }

    /** Totals for both windows, % changes, live listings and interest per listing. */
    private function summary(Collection $cur, Collection $prev, int $listings): array
    {
        $now = $this->totals($cur);
        $before = $this->totals($prev);

        return [
            'searches' => $this->count($now['searches'] + $now['saved_searches']),
            'views' => $this->count($now['views']),
            'favorites' => $this->count($now['favorites']),
            'enquiries' => $this->count($now['enquiries']),
            'interest' => $now['score'],
            'listings' => $listings,
            'interest_per_listing' => round($now['score'] / max(1, $listings), 2),
            'change' => [
                'searches' => $this->change($now['searches'] + $now['saved_searches'], $before['searches'] + $before['saved_searches']),
                'views' => $this->change($now['views'], $before['views']),
                'enquiries' => $this->change($now['enquiries'], $before['enquiries']),
                'interest' => $this->change($now['score'], $before['score']),
            ],
        ];
    }

    private function totals(Collection $rows): array
    {
        $totals = collect(self::METRICS)->mapWithKeys(fn ($m) => [$m => (int) $rows->sum($m)])->all();
        $totals['score'] = $this->score($totals);

        return $totals;
    }

    private function score(array $totals): int
    {
        return (int) collect(config('market_demand.weights'))->map(fn ($w, $m) => $w * (int) ($totals[$m] ?? 0))->sum();
    }

    /** High / Medium / Low: interest per listing against the baseline's. Null without enough activity. */
    private function level(array $summary, ?array $baseline): ?array
    {
        $c = config('market_demand');
        if (!$baseline || $summary['interest'] < $c['min_activity'] || $baseline['per_listing'] <= 0) {
            return null;
        }
        $ratio = round($summary['interest_per_listing'] / $baseline['per_listing'], 2);

        return [
            'key' => $ratio >= $c['high_ratio'] ? 'high' : ($ratio < $c['low_ratio'] ? 'low' : 'medium'),
            'ratio' => $ratio,
            'baseline' => $baseline['name'],
        ];
    }

    /**
     * Interest per live listing for the same segment (type / bedrooms when given, matched the same way
     * as the report: a search without that filter counts) across a city, or the whole platform, last
     * window. $communityRows: only activity tied to a community, as a community report's own rows are.
     */
    private function baseline(?string $city, string $purpose, ?string $type, ?int $beds, bool $communityRows): array
    {
        $from = today()->subDays((int) config('market_demand.window_days') - 1)->toDateString();
        $version = Cache::get('market-demand:version', 0);
        $key = 'market-demand-baseline:' . md5(json_encode([$city, $purpose, $type, $beds, $communityRows, $version]));

        return Cache::remember($key, 1800, function () use ($city, $purpose, $type, $beds, $communityRows, $from) {
            $sums = (array) DB::table('market_demand_daily')
                ->where('purpose', $purpose)->where('date', '>=', $from)
                ->when($city, fn ($q) => $q->where('city', $city), fn ($q) => $q->whereNotNull('city'))
                ->when($communityRows, fn ($q) => $q->whereNotNull('community'))
                ->when($type, fn ($q) => $q->where(fn ($w) => $w->whereNull('property_type')->orWhere('property_type', $type)))
                ->when($beds !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('bedrooms')->orWhere('bedrooms', $beds)))
                ->selectRaw(implode(', ', array_map(fn ($m) => "COALESCE(SUM({$m}), 0) as {$m}", self::METRICS)))
                ->first();
            $listings = Property::where('status', true)->available()
                ->when($purpose === 'rent', fn ($q) => $q->where('listing_type', 'rent'), fn ($q) => $q->where('listing_type', '!=', 'rent'))
                ->when($city, fn ($q) => $q->where('translations->en->city', $city))
                ->when($type, fn ($q) => $q->where('property_type', $type))
                ->get(['bedrooms'])
                ->filter(fn ($p) => $beds === null || DemandSegments::bedrooms($p->bedrooms) === $beds)
                ->count();

            return ['name' => $city, 'per_listing' => round($this->score($sums) / max(1, $listings), 4)];
        });
    }

    /**
     * Demand vs supply per bedroom count / price band: each value's share of interest against its
     * share of live listings. "Underserved" = demand share ≥ underserved_ratio × supply share.
     */
    private function breakdown(Collection $rows, Collection $supply, string $column, callable $supplyKey, ?int $focus, Carbon $curFrom, ?string $purpose = null): array
    {
        $c = config('market_demand');
        $demand = $rows->filter(fn ($r) => $r->{$column} !== null)->groupBy(fn ($r) => (int) $r->{$column});
        $listings = $supply->map($supplyKey)->filter(fn ($k) => $k !== null)->countBy();
        $keys = collect($demand->keys())->merge($listings->keys())->unique()->sort()->values();

        $scores = $keys->mapWithKeys(fn ($k) => [$k => $this->totals($demand->get($k, collect()))['score']]);
        $totalDemand = $scores->sum();
        $totalSupply = $listings->sum();
        $enough = $totalDemand >= $c['min_activity'];

        $items = $keys->map(function ($k) use ($demand, $listings, $scores, $totalDemand, $totalSupply, $enough, $c, $focus, $curFrom, $purpose) {
            $demandShare = $totalDemand ? $scores[$k] / $totalDemand : 0;
            $supplyShare = $totalSupply ? ($listings[$k] ?? 0) / $totalSupply : 0;
            $set = $demand->get($k, collect());
            $searches = fn ($s) => (int) $s->sum('searches') + (int) $s->sum('saved_searches');
            $now = $set->filter(fn ($r) => $r->date >= $curFrom);
            $before = $set->filter(fn ($r) => $r->date < $curFrom);

            return [
                'key' => (int) $k,
                'range' => $purpose ? DemandSegments::bandRange($purpose, (int) $k) : null,
                'demand_share' => $enough ? round($demandShare * 100, 1) : null,
                'supply_share' => round($supplyShare * 100, 1),
                'listings' => (int) ($listings[$k] ?? 0),
                'underserved' => $enough && $scores[$k] >= $c['min_change'] && $demandShare * 100 >= $c['underserved_min_share']
                    && $demandShare >= max($supplyShare, 0.0001) * $c['underserved_ratio'],
                'searches_change' => $this->change($searches($now), $searches($before)),
                'is_focus' => $focus !== null && (int) $k === $focus,
            ];
        })->values()->all();

        return ['enough' => $enough, 'items' => $items];
    }

    /** Searches, views and enquiries per month, oldest first. */
    private function trend(Collection $rows, Carbon $from): array
    {
        $months = [];
        for ($m = $from->copy(); $m <= today(); $m->addMonth()) {
            $set = $rows->filter(fn ($r) => $r->date->isSameMonth($m));
            $months[] = [
                'month' => $m->format('Y-m'),
                'searches' => (int) $set->sum('searches') + (int) $set->sum('saved_searches'),
                'views' => (int) $set->sum('views'),
                'enquiries' => (int) $set->sum('enquiries') + (int) $set->sum('favorites'),
            ];
        }

        return $months;
    }

    /**
     * Amenities people want here: searches asking for them (×3) plus views of listings that have
     * them, over config amenity_days. Labels come from the listings' own option labels.
     */
    private function amenities(?string $city, ?string $community, string $purpose, Collection $supply, array $has): array
    {
        $rows = DB::table('market_demand_amenities')
            ->where('purpose', $purpose)
            ->where('date', '>=', today()->subDays((int) config('market_demand.amenity_days') - 1)->toDateString())
            ->when($community, fn ($q) => $q->where('community', $community))
            ->when($city, fn ($q) => $q->where('city', $city))
            ->groupBy('amenity')
            ->selectRaw('amenity, SUM(searches) as searches, SUM(views) as views')
            ->get();

        $labels = [];
        $counts = [];
        foreach ($supply as $p) {
            foreach (DemandAggregator::amenityKeys($p) as $key) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
            foreach (array_merge((array) ($p->details?->amenities ?? []), (array) ($p->details?->easy_access ?? [])) as $option) {
                if (is_array($option) && isset($option['key']) && !isset($labels[$option['key']])) {
                    $labels[$option['key']] = $option['label'] ?? null;
                }
            }
        }

        $min = (int) config('market_demand.min_count');

        return $rows->map(fn ($r) => [
            'key' => $r->amenity,
            'label' => $labels[$r->amenity] ?? null, // {en, ar} or null → the page names it
            'searches' => (int) $r->searches,
            'views' => (int) $r->views,
            'listings' => $counts[$r->amenity] ?? 0,
            'score' => 3 * (int) $r->searches + (int) $r->views,
            'has' => in_array($r->amenity, $has, true),
        ])
            ->filter(fn ($a) => $a['searches'] + $a['views'] >= $min)
            ->sortByDesc('score')->take(6)->values()
            ->map(fn ($a) => ['searches' => $this->count($a['searches']), 'views' => $this->count($a['views'])] + array_diff_key($a, ['score' => 0]))
            ->all();
    }

    /**
     * Price benchmark for the listing's segment (same type and bedrooms, live, this area): median and
     * average asking price, where this listing sits, plus the area's price per sq.ft and 12-month change
     * from the Market Insights figures.
     */
    private function pricing(Property $property, Collection $typeSupply, array $focus): array
    {
        $min = (int) config('property_insights.min_sample');
        $peers = $typeSupply->filter(fn ($p) => $p->id !== $property->id && $p->price > 0
            && ($focus['beds'] === null || DemandSegments::bedrooms($p->bedrooms) === $focus['beds']))
            ->map(fn ($p) => DemandSegments::annualPrice($p))->sort()->values();
        $own = DemandSegments::annualPrice($property);

        $median = null;
        if ($peers->count() >= $min) {
            $n = $peers->count();
            $median = $n % 2 ? $peers[intdiv($n, 2)] : ($peers[$n / 2 - 1] + $peers[$n / 2]) / 2;
        }
        $market = $this->insights->forSlug($property->slug) ?? [];

        return [
            'count' => $peers->count(),
            'median' => $median ? round($median, -3) : null,
            'average' => $peers->count() >= $min ? round($peers->avg(), -3) : null,
            'vs_median_pct' => $median && $own > 0 ? round(($own - $median) / $median * 100) : null,
            'median_psf' => $market['market']['asking']['median_psf'] ?? null,
            'psf_count' => $market['market']['asking']['count'] ?? null,
            'growth' => $market['estimates']['growth'] ?? null,
        ];
    }

    /**
     * Key findings, most telling first, as {key, tone, …values} — the page words them (and translates
     * them), and they are what the AI summary is written from.
     */
    private function insightList(array $report, ?array $focus): array
    {
        $out = [];
        $seg = $report['segment'] ?? null;
        $area = $report['area_demand'];
        $main = $seg ?? $area;
        $threshold = 10; // % change worth mentioning

        if (($p = $main['change']['searches']) !== null && abs($p) >= $threshold) {
            $out[] = ['key' => $seg ? ($p > 0 ? 'segment_searches_up' : 'segment_searches_down') : ($p > 0 ? 'area_searches_up' : 'area_searches_down'), 'tone' => $p > 0 ? 'positive' : 'caution', 'value' => abs($p)];
        } elseif (($p = $main['change']['views']) !== null && abs($p) >= $threshold) {
            $out[] = ['key' => $seg ? ($p > 0 ? 'segment_views_up' : 'segment_views_down') : ($p > 0 ? 'area_views_up' : 'area_views_down'), 'tone' => $p > 0 ? 'positive' : 'caution', 'value' => abs($p)];
        }

        if ($level = $main['level']) {
            if ($level['key'] !== 'medium' && $level['baseline']) {
                $out[] = ['key' => 'level_' . $level['key'], 'tone' => $level['key'] === 'high' ? 'positive' : 'caution', 'value' => $level['ratio'], 'place' => $level['baseline']];
            } elseif ($level['key'] !== 'medium') {
                $out[] = ['key' => 'level_' . $level['key'] . '_platform', 'tone' => $level['key'] === 'high' ? 'positive' : 'caution', 'value' => $level['ratio']];
            }
        }

        if (($p = $main['change']['enquiries']) !== null && abs($p) >= $threshold) {
            $out[] = ['key' => $p > 0 ? 'enquiries_up' : 'enquiries_down', 'tone' => $p > 0 ? 'positive' : 'caution', 'value' => abs($p)];
        }

        $bands = collect($report['price_bands']['items'])->where('underserved', true)->sortByDesc(fn ($b) => $b['demand_share'] - $b['supply_share']);
        if ($focus && $focus['band'] !== null && $bands->firstWhere('key', $focus['band'])) {
            $out[] = ['key' => 'own_band_underserved', 'tone' => 'positive', 'range' => DemandSegments::bandRange($report['purpose'], $focus['band'])];
        } elseif ($top = $bands->first()) {
            $out[] = ['key' => 'band_underserved', 'tone' => 'neutral', 'range' => $top['range'], 'demand' => $top['demand_share'], 'supply' => $top['supply_share']];
        }

        $beds = collect($report['bedrooms']['items'])->where('underserved', true)->sortByDesc(fn ($b) => $b['demand_share'] - $b['supply_share']);
        if ($top = $beds->first()) {
            $out[] = ['key' => 'beds_underserved', 'tone' => 'neutral', 'beds' => $top['key'], 'demand' => $top['demand_share'], 'supply' => $top['supply_share']];
        }
        // "4-bedroom searches up 35%" for a bedroom count other than the listing's.
        if (!$seg && ($rising = collect($report['bedrooms']['items'])->filter(fn ($b) => ($b['searches_change'] ?? 0) >= 20)->sortByDesc('searches_change')->first())) {
            $out[] = ['key' => 'beds_searches_up', 'tone' => 'positive', 'beds' => $rising['key'], 'value' => $rising['searches_change']];
        }

        if ($amenity = collect($report['amenities'])->first()) {
            $out[] = ['key' => 'top_amenity', 'tone' => 'neutral', 'amenity' => $amenity['key'], 'label' => $amenity['label'], 'has' => $amenity['has']];
        }

        if ($focus && ($pct = $report['pricing']['vs_median_pct'] ?? null) !== null && abs($pct) >= 8) {
            $out[] = ['key' => $pct < 0 ? 'priced_below_segment' : 'priced_above_segment', 'tone' => $pct < 0 ? 'positive' : 'caution', 'value' => abs($pct)];
        }

        return array_slice($out, 0, 6);
    }

    private function change(int $now, int $before): ?int
    {
        $min = (int) config('market_demand.min_change');
        if ($now < $min || $before < $min) {
            return null;
        }

        return (int) round(($now - $before) / $before * 100);
    }

    /** Small counts are hidden so no single visitor's activity can be picked out. */
    private function count(int $n): ?int
    {
        return $n > 0 && $n < (int) config('market_demand.min_count') ? null : $n;
    }

    /** {en: 'Villa', ar: 'فيلا'} from Admin › Filters (property_type). */
    private function typeLabel(string $type): array
    {
        $value = $this->labels->values()['property_type'][$type] ?? null;
        $translations = collect($value?->translations ?? [])->map(fn ($t) => $t['label'] ?? null)->filter()->all();

        return $translations ?: ['en' => ucwords(str_replace(['-', '_'], ' ', $type))];
    }
}
