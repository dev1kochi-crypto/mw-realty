<?php

namespace App\Services\MarketDemand;

use App\Models\Property;
use App\Models\Visitors\VisitorEvent;
use App\Services\PropertiesPageService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rolls one day of website activity (visitor_events) into the anonymous demand tables
 * (market_demand_daily, market_demand_amenities). Run by `market-demand:aggregate` every hour for
 * today and yesterday, so the figures stay fresh and the raw events are counted before
 * visitors:prune removes anonymous ones (30 days).
 *
 *   search / saved_search    → the filters used: area, purpose, type(s), bedrooms, price band, amenities
 *   property_view            → the listing's segment (and its amenities)
 *   favorite_added           → the listing's segment
 *   contact_click / enquiry  → the listing's segment, as an enquiry
 *
 * Each count is the number of DIFFERENT browsers that day (one person refreshing ten times = 1).
 * Re-running a day replaces its rows, so the job is safe to repeat.
 */
class DemandAggregator
{
    private const TYPES = [
        VisitorEvent::SEARCH => 'searches',
        VisitorEvent::SAVED_SEARCH => 'saved_searches',
        VisitorEvent::PROPERTY_VIEW => 'views',
        VisitorEvent::FAVORITE_ADDED => 'favorites',
        VisitorEvent::CONTACT_CLICK => 'enquiries',
        VisitorEvent::ENQUIRY => 'enquiries',
    ];

    private const METRICS = ['searches', 'saved_searches', 'views', 'favorites', 'enquiries'];

    /** Lookups from a search's location text to an area: lower(name) → [city, community]. */
    private array $communities = [];
    private array $cities = [];
    /** property id → its segment (or false when it has none), cached for the run. */
    private array $properties = [];

    /** @return array{events: int, rows: int, amenity_rows: int} */
    public function aggregate(Carbon $date): array
    {
        $this->loadAreas();
        $day = $date->copy()->startOfDay();
        $cells = [];
        $amenityCells = [];
        $events = 0;

        VisitorEvent::query()
            ->whereIn('type', array_keys(self::TYPES))
            ->whereBetween('created_at', [$day, $day->copy()->endOfDay()])
            ->select(['id', 'type', 'visitor_browser_id', 'visitor_lead_id', 'property_id', 'meta'])
            ->chunkById(2000, function ($chunk) use (&$cells, &$amenityCells, &$events) {
                $this->loadProperties($chunk->pluck('property_id')->filter()->unique()->all());

                foreach ($chunk as $event) {
                    $metric = self::TYPES[$event->type];
                    $who = $event->visitor_browser_id ? 'b' . $event->visitor_browser_id : ($event->visitor_lead_id ? 'l' . $event->visitor_lead_id : 'e' . $event->id);

                    if (in_array($event->type, [VisitorEvent::SEARCH, VisitorEvent::SAVED_SEARCH], true)) {
                        [$segments, $amenities, $area] = $this->fromFilters((array) ($event->meta['filters'] ?? []));
                        $amenityMetric = 'searches';
                    } else {
                        $segment = $event->property_id ? ($this->properties[$event->property_id] ?? false) : false;
                        if (!$segment) {
                            continue;
                        }
                        $segments = [$segment['dims']];
                        $amenities = $event->type === VisitorEvent::PROPERTY_VIEW ? $segment['amenities'] : [];
                        $area = $segment['dims'];
                        $amenityMetric = 'views';
                    }

                    $events++;
                    foreach ($segments as $dims) {
                        $cells[implode('|', $dims)][$metric][$who] = true;
                    }
                    foreach ($amenities as $amenity) {
                        $key = implode('|', [$area['city'], $area['community'], $area['purpose'], $amenity]);
                        $amenityCells[$key][$amenityMetric][$who] = true;
                    }
                }
            });

        $date = $day->toDateString();
        $rows = collect($cells)->map(function ($metrics, $key) use ($date) {
            [$city, $community, $purpose, $type, $beds, $band] = explode('|', $key);

            return [
                'date' => $date,
                'city' => $city ?: null,
                'community' => $community ?: null,
                'purpose' => $purpose ?: null,
                'property_type' => $type ?: null,
                'bedrooms' => $beds === '' ? null : (int) $beds,
                'price_band' => $band === '' ? null : (int) $band,
            ] + collect(self::METRICS)->mapWithKeys(fn ($m) => [$m => count($metrics[$m] ?? [])])->all();
        })->values();

        $amenityRows = collect($amenityCells)->map(function ($metrics, $key) use ($date) {
            [$city, $community, $purpose, $amenity] = explode('|', $key);

            return [
                'date' => $date,
                'city' => $city ?: null,
                'community' => $community ?: null,
                'purpose' => $purpose ?: null,
                'amenity' => $amenity,
                'searches' => count($metrics['searches'] ?? []),
                'views' => count($metrics['views'] ?? []),
            ];
        })->values();

        DB::transaction(function () use ($date, $rows, $amenityRows) {
            DB::table('market_demand_daily')->where('date', $date)->delete();
            DB::table('market_demand_amenities')->where('date', $date)->delete();
            foreach ($rows->chunk(500) as $chunk) {
                DB::table('market_demand_daily')->insert($chunk->all());
            }
            foreach ($amenityRows->chunk(500) as $chunk) {
                DB::table('market_demand_amenities')->insert($chunk->all());
            }
        });

        return ['events' => $events, 'rows' => $rows->count(), 'amenity_rows' => $amenityRows->count()];
    }

    /**
     * A search's filters → its segments (one per property type × bedroom value asked for), the
     * amenity option keys it asked for, and its area.
     *
     * @return array{0: array<int, array>, 1: string[], 2: array}
     */
    private function fromFilters(array $filters): array
    {
        $value = fn (string $key) => is_array($filters[$key] ?? null) ? implode(',', array_filter($filters[$key], 'is_scalar')) : (string) ($filters[$key] ?? '');
        $list = fn (string $key) => array_values(array_filter(array_map('trim', explode(',', $value($key))), fn ($v) => $v !== ''));

        [$city, $community] = $this->resolveArea($value('community'), $value('city'), $value('location'));
        $purpose = DemandSegments::purpose($value('listing_type') ?: $value('category'));

        $min = is_numeric($value('min_price')) ? (float) $value('min_price') : null;
        $max = is_numeric($value('max_price')) ? (float) $value('max_price') : null;
        $point = $min !== null && $max !== null ? ($min + $max) / 2 : ($max ?? $min);
        $band = DemandSegments::priceBand($purpose, $point);

        $types = array_map(fn ($t) => mb_substr($t, 0, 50), array_slice($list('property_type'), 0, 6)) ?: [null];
        $beds = array_values(array_unique(array_filter(array_map([DemandSegments::class, 'bedrooms'], array_slice($list('bedrooms'), 0, 8)), fn ($b) => $b !== null))) ?: [null];

        $segments = [];
        foreach ($types as $type) {
            foreach ($beds as $bed) {
                $segments[] = ['city' => $city, 'community' => $community, 'purpose' => $purpose, 'type' => $type, 'beds' => $bed, 'band' => $band];
            }
        }

        $amenities = collect($list('amenities'))
            ->flatMap(fn ($a) => PropertiesPageService::AMENITY_OPTIONS[$a][1] ?? (in_array($a, ['furnished', 'parking'], true) ? [$a] : []))
            ->unique()->values()->all();

        return [$segments, $amenities, ['city' => $city, 'community' => $community, 'purpose' => $purpose]];
    }

    /** Picked community / city first, then free text matched against known communities and cities. */
    private function resolveArea(string $community, string $city, string $text): array
    {
        $key = fn (string $v) => mb_strtolower(trim($v));

        if ($community !== '' && isset($this->communities[$key($community)])) {
            return $this->communities[$key($community)];
        }
        if ($city !== '' && isset($this->cities[$key($city)])) {
            return [$this->cities[$key($city)], null];
        }
        if ($text !== '') {
            $text = $key($text);
            if (isset($this->communities[$text])) {
                return $this->communities[$text];
            }
            if (isset($this->cities[$text])) {
                return [$this->cities[$text], null];
            }
        }

        return [null, null];
    }

    /** Every community / city on the platform, by English name, Arabic name and location slug. */
    private function loadAreas(): void
    {
        $this->communities = [];
        $this->cities = [];
        Property::query()->select(['id', 'translations', 'location'])->chunkById(2000, function ($chunk) {
            foreach ($chunk as $p) {
                $area = DemandSegments::area($p);
                if ($area['city']) {
                    foreach (['en', 'ar'] as $lang) {
                        if ($name = DemandSegments::clean($p->translations[$lang]['city'] ?? null)) {
                            $this->cities[mb_strtolower($name)] ??= $area['city'];
                        }
                    }
                }
                if ($area['community']) {
                    $names = [$p->translations['ar']['community'] ?? null, $p->location, $area['community']];
                    foreach (array_filter($names) as $name) {
                        $this->communities[mb_strtolower(DemandSegments::clean($name))] ??= [$area['city'], $area['community']];
                    }
                }
            }
        });
    }

    private function loadProperties(array $ids): void
    {
        $missing = array_diff($ids, array_keys($this->properties));
        if (!$missing) {
            return;
        }

        Property::with('details')->whereIn('id', $missing)
            ->get(['id', 'translations', 'listing_type', 'property_type', 'bedrooms', 'price', 'rental_period'])
            ->each(function (Property $p) {
                $area = DemandSegments::area($p);
                $purpose = DemandSegments::purpose($p->listing_type);
                $this->properties[$p->id] = $purpose ? [
                    'dims' => [
                        'city' => $area['city'], 'community' => $area['community'], 'purpose' => $purpose,
                        'type' => $p->property_type ? mb_substr($p->property_type, 0, 50) : null,
                        'beds' => DemandSegments::bedrooms($p->bedrooms),
                        'band' => DemandSegments::priceBand($purpose, DemandSegments::annualPrice($p)),
                    ],
                    'amenities' => self::amenityKeys($p),
                ] : false;
            });

        foreach ($missing as $id) {
            $this->properties[$id] ??= false; // deleted listing
        }
    }

    /** The listing's amenity and "easy access" option keys (pool, gym, metro station…). */
    public static function amenityKeys(Property $p): array
    {
        $details = $p->details;
        $keys = collect(array_merge((array) ($details?->amenities ?? []), (array) ($details?->easy_access ?? [])))
            ->map(fn ($a) => is_array($a) ? ($a['key'] ?? null) : null)->filter();
        if ($details?->isFurnished()) {
            $keys->push('furnished');
        }
        if ((int) ($details?->parking ?? 0) > 0) {
            $keys->push('parking');
        }

        return $keys->map(fn ($k) => mb_substr((string) $k, 0, 60))->unique()->values()->all();
    }
}
