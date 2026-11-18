<?php

namespace App\Services\Chatbot;

use App\Models\FilterValue;
use App\Models\NearbyPlace;
use App\Models\Property;
use App\Support\LocationFilter;
use App\Support\MapsPropertyCards;
use Illuminate\Support\Facades\Cache;

/**
 * Ranks live listings against the buyer's brief the AI chat captured (ChatbotService) instead of
 * only filtering them: listing type, property type and location narrow the pool; budget,
 * bedrooms, size, nearby places (schools, metro, …), furnishing and investment purpose are scored,
 * so a near miss (one bedroom more, a little over budget) still shows — lower down, with the
 * reason. Service charge and commute aren't in the listing data: they're kept in the brief for the
 * agent, never scored.
 */
class PropertyMatcher
{
    use MapsPropertyCards;

    /** Cards shown in the chat. */
    public const RESULTS = 6;
    /** Listings scored per search — the closest by price / bedrooms are fetched first. */
    private const CANDIDATES = 300;
    /** How far over the top of the budget a listing can be and still be shown. */
    private const BUDGET_STRETCH = 0.15;
    /** A named place ("Aster Clinic JVC") counts as near up to this far away (straight line). */
    private const PLACE_RADIUS_KM = 5;
    /** Most named places / keywords one search scores against. */
    private const MAX_TERMS = 5;

    /**
     * Every requirement the visitor gave is a hard filter first (exact bedrooms, min bathrooms,
     * property type, budget, nearby place types, the named place tagged on the listing's Location &
     * Nearby tab, location). When that matches nothing they're loosened one by one — least important
     * first — so the visitor always sees the closest related listings instead of "no matches".
     * 'place_tag' loosens the named place from "tagged on the listing" to "within PLACE_RADIUS_KM".
     * Listing type (buy / rent / off-plan) is never dropped: a rental is no answer to a buyer.
     */
    private const RELAX_ORDER = ['bathrooms', 'nearby', 'bedrooms', 'budget', 'property_type', 'place_tag', 'place', 'location'];

    /** Criterion => weight (only the criteria the buyer actually gave count). */
    private const WEIGHTS = [
        'budget' => 30,
        'place' => 25,
        'bedrooms' => 25,
        'nearby' => 20,
        'keywords' => 10,
        'purpose' => 10,
        'area' => 10,
        'bathrooms' => 5,
        'furnished' => 5,
    ];

    /**
     * @param  array  $brief  sanitized brief (ChatbotService::sanitizeArgs())
     * @return array{properties: array, total: int}  cards carry a `match` {score, reasons, misses}
     */
    public function match(array $brief, string $lang, array $nearbyLabels = []): array
    {
        $places = $this->resolvePlaces($brief['places'] ?? []);
        // A name that isn't a known landmark ("Sobha Hartland", "Burj view") is still looked for —
        // as words in the listing text.
        $keywords = array_slice(array_values(array_unique(array_merge($brief['keywords'] ?? [], $places['unknown']))), 0, self::MAX_TERMS);

        $relaxed = [];
        $candidates = $this->candidates($brief, $places['found'], []);
        foreach (self::RELAX_ORDER as $filter) {
            if ($candidates->isNotEmpty()) {
                break;
            }
            if ($this->hasFilter($filter, $brief, $places['found'])) {
                $relaxed[] = $filter;
                $candidates = $this->candidates($brief, $places['found'], $relaxed);
            }
        }
        // Put back any loosened filter the last one made unnecessary ("1 bed" dropped first, then
        // "apartment" — a 1-bed penthouse may exist), so only what truly blocks stays loosened.
        foreach (array_slice($relaxed, 0, -1) as $filter) {
            $without = array_values(array_diff($relaxed, [$filter]));
            if ($filter === 'place_tag' && in_array('place', $without, true)) {
                continue; // the radius step only exists once tagged-only is loosened
            }
            $retry = $this->candidates($brief, $places['found'], $without);
            if ($retry->isNotEmpty()) {
                [$relaxed, $candidates] = [$without, $retry];
            }
        }

        $ranked = $candidates->map(fn (Property $property) => [$property, $this->score($property, $brief, $nearbyLabels, $places['found'], $keywords)])
            ->sortByDesc(fn ($row) => $row[1]['score'] ?? 0)
            ->take(self::RESULTS);

        return [
            'properties' => $ranked->map(fn ($row) => $this->mapProperty($row[0], $lang) + ['match' => $row[1]])->values()->all(),
            'total' => $candidates->count(),
            // Hard filters dropped to find these — the AI tells the visitor they're the closest, not exact.
            'relaxed' => $relaxed,
            'places_found' => array_map(fn (NearbyPlace $p) => $p->getTranslation('name', 'en'), $places['found']),
        ];
    }

    private function hasFilter(string $filter, array $brief, array $places): bool
    {
        return match ($filter) {
            'bathrooms' => !empty($brief['bathrooms']),
            'nearby' => !empty($brief['nearby']),
            'bedrooms' => !empty($brief['bedrooms']),
            'budget' => !empty($brief['max_price']) || !empty($brief['min_price']),
            'property_type' => !empty($brief['property_type']),
            'place_tag', 'place' => (bool) $places,
            'location' => !empty($brief['location']),
            default => false,
        };
    }

    /** The pool to score: the brief's hard filters minus the ones in $relaxed. */
    private function candidates(array $brief, array $places, array $relaxed)
    {
        $query = Property::where('status', true)->residential()
            ->with(['details', 'nearbyPlaces' => fn ($q) => $q->select('nearby_places.id', 'category')]);
        $off = fn (string $filter) => in_array($filter, $relaxed, true);

        $category = $brief['category'] ?? null;
        $query->when($category === 'off_plan', fn ($q) => $q->where('completion_status', 'off_plan'))
            ->when(in_array($category, ['sale', 'rent'], true), fn ($q) => $q->where('listing_type', $category))
            // An investor buys; a rental listing can't earn them rental income.
            ->when(!$category && ($brief['purpose'] ?? null) === 'invest', fn ($q) => $q->where('listing_type', 'sale'))
            ->when(!$off('property_type') && !empty($brief['property_type']), fn ($q) => $q->where('property_type', $brief['property_type']));
        if (!$off('location') && !empty($brief['location'])) {
            $this->applyLocation($query, $brief['location']);
        }
        if (!$off('nearby') && !empty($brief['nearby'])) {
            // Every place type asked for ("near a school and the metro") listed on the listing.
            foreach ($brief['nearby'] as $type) {
                $query->whereHas('nearbyPlaces', fn ($q) => $q->where('category', $type));
            }
        }
        if (!$off('place_tag') && $places) {
            // The named place is on the listing's Location & Nearby tab.
            $query->whereHas('nearbyPlaces', fn ($q) => $q->whereIn('nearby_places.id', array_map(fn ($p) => $p->id, $places)));
        } elseif (!$off('place') && $places) {
            // None tagged: within the radius of any named place (a box here — the exact distance is scored).
            $query->where(function ($q) use ($places) {
                foreach ($places as $place) {
                    $lat = (float) $place->latitude;
                    $lng = (float) $place->longitude;
                    $dLat = self::PLACE_RADIUS_KM / 111;
                    $dLng = self::PLACE_RADIUS_KM / (111 * max(0.1, cos(deg2rad($lat))));
                    $q->orWhere(fn ($b) => $b->whereBetween('latitude', [$lat - $dLat, $lat + $dLat])
                        ->whereBetween('longitude', [$lng - $dLng, $lng + $dLng]));
                }
            });
        }

        // Budget keeps a small stretch (a near miss is shown lower down, with the reason); once
        // relaxed it's only scored. Rooms are exact: "1 bed" means 1 bed, "5+" five or more.
        $max = $off('budget') ? null : ($brief['max_price'] ?? null);
        $min = $off('budget') ? null : ($brief['min_price'] ?? null);
        $beds = $off('bedrooms') ? null : $this->rooms($brief['bedrooms'] ?? null);
        $baths = $off('bathrooms') ? null : $this->rooms($brief['bathrooms'] ?? null);
        $query->when($max, fn ($q) => $q->where(fn ($q) => $q->whereNull('price')->orWhere('price', '<=', $max * (1 + self::BUDGET_STRETCH))))
            ->when($min, fn ($q) => $q->where(fn ($q) => $q->whereNull('price')->orWhere('price', '>=', $min * 0.8)))
            ->when($beds !== null, fn ($q) => $brief['bedrooms'] === '5+' ? $q->where('bedrooms', '>=', 5) : $q->where('bedrooms', $beds))
            ->when($baths !== null, fn ($q) => $q->where('bathrooms', '>=', $baths));

        return $query->orderByDesc('featured')->orderByDesc('published_at')->limit(self::CANDIDATES)->get();
    }

    /**
     * Named places the visitor mentioned ("Aster Clinic", "Dubai Mall", "JSS school") → the
     * landmarks (Master › Nearby Places) they mean: best word match on name + address, via
     * NameMatcher (typos, short forms like "JVC" read off the names). "Aster Clinic" alone keeps
     * every Aster clinic; "Aster Clinic JVC" just that one.
     *
     * @param  string[]  $names
     * @return array{found: NearbyPlace[], unknown: string[]}
     */
    private function resolvePlaces(array $names): array
    {
        $found = [];
        $unknown = [];
        $index = $this->placeIndex();
        foreach (array_slice($names, 0, self::MAX_TERMS) as $name) {
            $words = NameMatcher::words($name);
            if (!$words) {
                continue;
            }
            $phrase = implode(' ', $words);
            $scored = collect($index)->map(function ($place) use ($words, $phrase) {
                $matches = fn (array $nameWords) => count(array_filter($words, fn ($w) => NameMatcher::wordMatches($w, $nameWords)));

                return [
                    'id' => $place['id'],
                    'hits' => $matches(array_merge($place['name'], $place['address'])),
                    'name_hits' => $matches($place['name']),
                    'phrase' => str_contains(implode(' ', $place['name']), $phrase),
                ];
            })->filter(fn ($row) => $row['name_hits'] > 0);
            // The name as typed, words in order ("Dubai Mall" ≠ "Dubai Marina Mall"), wins outright.
            if ($scored->contains('phrase', true)) {
                $scored = $scored->where('phrase', true);
            }

            // Name words first ("Dubai Mall": "The Dubai Mall" beats a mall merely *in* Dubai), address breaks ties.
            $bestName = $scored->max('name_hits');
            $scored = $scored->where('name_hits', $bestName);
            $best = $scored->max('hits');
            if (!$best || $best < max(1, (int) ceil(count($words) / 2))) {
                $unknown[] = $name;
                continue;
            }
            foreach ($scored->where('hits', $best) as $row) {
                $found[$row['id']] = true;
            }
        }

        $places = $found ? NearbyPlace::whereIn('id', array_keys($found))->get()->all() : [];

        return ['found' => $places, 'unknown' => $unknown];
    }

    /** Every active, mapped nearby place as name / address words (all languages) — cached, as
     *  places are matched in PHP (typos and short forms can't be a SQL LIKE). */
    private function placeIndex(): array
    {
        return Cache::remember('chatbot:place-index', 600, fn () => NearbyPlace::active()
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->get(['id', 'translations'])
            ->map(fn (NearbyPlace $p) => [
                'id' => $p->id,
                'name' => NameMatcher::words(implode(' ', array_map(fn ($t) => $t['name'] ?? '', $p->translations ?? []))),
                'address' => NameMatcher::words(implode(' ', array_map(fn ($t) => $t['address'] ?? '', $p->translations ?? []))),
            ])
            ->all());
    }

    /**
     * The real location names visitors can mean — every community and city on an active listing
     * plus the Location values in Master › Property Options, all languages. Cached; the chat's
     * AI also gets the English list so it picks a real name ("JVC" → "Jumeirah Village Circle").
     *
     * @return string[]
     */
    public static function knownLocations(?string $lang = null): array
    {
        $all = Cache::remember('chatbot:known-locations', 3600, function () {
            $names = ['en' => [], 'other' => []];
            // The languages LocationFilter searches (only the two fields, not whole translations).
            foreach (['en', 'ar'] as $code) {
                foreach (['community', 'city'] as $field) {
                    Property::where('status', true)->residential()
                        ->selectRaw("DISTINCT JSON_UNQUOTE(JSON_EXTRACT(translations, '$.\"{$code}\".\"{$field}\"')) AS name")
                        ->pluck('name')
                        ->filter(fn ($n) => $n && $n !== 'null')
                        ->each(function ($n) use (&$names, $code) {
                            $names[$code === 'en' ? 'en' : 'other'][] = trim($n);
                        });
                }
            }
            FilterValue::whereHas('filter', fn ($q) => $q->where('key', 'location'))->active()->get()
                ->each(function ($value) use (&$names) {
                    foreach ($value->translations ?? [] as $code => $t) {
                        if (!empty($t['label'])) {
                            $names[$code === 'en' ? 'en' : 'other'][] = trim($t['label']);
                        }
                    }
                });

            return array_map(fn ($list) => array_values(array_unique($list)), $names);
        });

        return $lang === 'en' ? $all['en'] : array_values(array_unique(array_merge($all['en'], $all['other'])));
    }

    /**
     * What the visitor typed ("JVC", "jumeira village", "Aster Clinic JVC", "Marina") → the real
     * location names it means. A name whose every word is covered wins (the most specific one —
     * "Jumeirah Village Circle" over "Dubai" in "JVC, Dubai"); else the names most of its words hit.
     *
     * @return string[]
     */
    private function resolveLocation(string $text): array
    {
        $words = NameMatcher::words($text);
        if (!$words) {
            return [];
        }
        $rows = collect(self::knownLocations())->map(fn ($name) => ['name' => $name, 'words' => NameMatcher::words($name)])
            ->filter(fn ($row) => $row['words'])
            ->map(fn ($row) => $row + NameMatcher::compare($words, $row['words']));

        $covered = $rows->where('covered', true);
        if ($covered->isNotEmpty()) {
            $most = $covered->max(fn ($row) => count($row['words']));

            return $covered->filter(fn ($row) => count($row['words']) === $most)->pluck('name')->values()->all();
        }
        $best = $rows->max('hits');

        return $best ? $rows->where('hits', $best)->pluck('name')->values()->all() : [];
    }

    /** Straight-line km between two points. */
    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** @return array{score: ?int, reasons: string[], misses: string[]} score null = nothing to score on */
    private function score(Property $property, array $brief, array $nearbyLabels, array $places = [], array $keywords = []): array
    {
        $earned = 0;
        $possible = 0;
        $reasons = [];
        $misses = [];
        $add = function (string $criterion, float $fit, ?string $reason = null, ?string $miss = null) use (&$earned, &$possible, &$reasons, &$misses) {
            $possible += self::WEIGHTS[$criterion];
            $earned += self::WEIGHTS[$criterion] * max(0, min(1, $fit));
            if ($fit >= 0.99 && $reason) {
                $reasons[] = $reason;
            } elseif ($fit < 0.99 && $miss) {
                $misses[] = $miss;
            }
        };

        $price = $property->price ? (float) $property->price : null;
        $max = $brief['max_price'] ?? null;
        $min = $brief['min_price'] ?? null;
        if ($max || $min) {
            if ($price === null) {
                $add('budget', 0.5, null, 'Price on request');
            } elseif ($max && $price > $max) {
                $over = ($price - $max) / $max;
                $add('budget', 1 - $over / self::BUDGET_STRETCH, null, 'AED ' . number_format($price - $max) . ' over budget');
            } elseif ($min && $price < $min) {
                $add('budget', 0.7, null, 'Below the budget range');
            } else {
                $add('budget', 1, 'Within budget');
            }
        }

        $beds = $this->rooms($brief['bedrooms'] ?? null);
        if ($beds !== null) {
            $has = (int) $property->bedrooms;
            $exact = $brief['bedrooms'] === '5+' ? $has >= 5 : $has === $beds;
            $label = $has === 0 ? 'Studio' : "{$has} bedroom" . ($has > 1 ? 's' : '');
            $add('bedrooms', $exact ? 1 : (abs($has - $beds) === 1 ? 0.4 : 0), $label, "{$label} (asked {$brief['bedrooms']})");
        }

        $baths = $this->rooms($brief['bathrooms'] ?? null);
        if ($baths !== null) {
            $add('bathrooms', (int) $property->bathrooms >= $baths ? 1 : 0, null, 'Fewer bathrooms');
        }

        if (!empty($brief['min_area'])) {
            $sqft = (float) $property->sqft;
            $add('area', $sqft >= $brief['min_area'] ? 1 : ($sqft >= $brief['min_area'] * 0.9 ? 0.5 : 0),
                number_format($sqft) . ' sq.ft', $sqft ? 'Smaller (' . number_format($sqft) . ' sq.ft)' : 'Size not listed');
        }

        if (!empty($brief['nearby'])) {
            $near = $property->nearbyPlaces->pluck('category')->all();
            $found = array_values(array_intersect($brief['nearby'], $near));
            $missing = array_values(array_diff($brief['nearby'], $near));
            $label = fn (array $types) => implode(', ', array_map(fn ($t) => mb_strtolower($nearbyLabels[$t] ?? str_replace('_', ' ', $t)), $types));
            $possible += self::WEIGHTS['nearby'];
            $earned += self::WEIGHTS['nearby'] * count($found) / count($brief['nearby']);
            if ($found) {
                $reasons[] = 'Near ' . $label($found);
            }
            if ($missing) {
                $misses[] = 'No ' . $label($missing) . ' listed nearby';
            }
        }

        if ($places) {
            // Tagged on the listing's Location & Nearby tab = a full match; otherwise scored by
            // distance. The km is the same straight-line distance that tab shows.
            $tagged = $property->nearbyPlaces->pluck('id')->all();
            $km = fn (NearbyPlace $p) => $property->latitude && $property->longitude
                ? $this->distanceKm((float) $property->latitude, (float) $property->longitude, (float) $p->latitude, (float) $p->longitude)
                : null;
            $listed = array_values(array_filter($places, fn ($p) => in_array($p->id, $tagged, true)));
            $possible += self::WEIGHTS['place'];
            if ($listed) {
                $earned += self::WEIGHTS['place'];
                $d = $km($listed[0]);
                $reasons[] = $listed[0]->getTranslation('name', 'en') . ' nearby' . ($d !== null ? ' (' . number_format($d, 1) . ' km)' : '');
            } else {
                $nearest = null;
                foreach ($places as $place) {
                    $d = $km($place);
                    if ($d !== null && ($nearest === null || $d < $nearest[1])) {
                        $nearest = [$place, $d];
                    }
                }
                $placeName = ($nearest[0] ?? $places[0])->getTranslation('name', 'en');
                $d = $nearest[1] ?? null;
                // Below a tagged listing even when close: it isn't on the listing's nearby list.
                $earned += self::WEIGHTS['place'] * match (true) {
                    $d === null => 0,
                    $d <= 1 => 0.8,
                    $d <= 3 => 0.6,
                    $d <= self::PLACE_RADIUS_KM => 0.4,
                    default => max(0, 0.3 - ($d - self::PLACE_RADIUS_KM) / 50),
                };
                $misses[] = $d === null ? "Distance to {$placeName} unknown"
                    : number_format($d, 1) . " km from {$placeName} (not on its nearby list)";
            }
        }

        if ($keywords) {
            $text = mb_strtolower(implode(' ', array_map(fn ($f) => (string) $property->getTranslation($f, 'en'), ['title', 'key_features', 'description', 'address', 'community', 'city'])));
            // Every meaningful word of a keyword must appear ("sea view" → sea + view).
            $hit = array_values(array_filter($keywords, function ($keyword) use ($text) {
                $words = NameMatcher::words($keyword);

                return $words && count(array_filter($words, fn ($w) => str_contains($text, $w))) === count($words);
            }));
            $possible += self::WEIGHTS['keywords'];
            $earned += self::WEIGHTS['keywords'] * count($hit) / count($keywords);
            if ($hit) {
                $reasons[] = 'Mentions ' . implode(', ', $hit);
            }
            if ($missing = array_diff($keywords, $hit)) {
                $misses[] = 'No mention of ' . implode(', ', $missing);
            }
        }

        if (isset($brief['furnished'])) {
            $add('furnished', (bool) $property->details?->isFurnished() === $brief['furnished'] ? 1 : 0,
                $brief['furnished'] ? 'Furnished' : 'Unfurnished', $brief['furnished'] ? 'Unfurnished' : 'Furnished');
        }

        if (($brief['purpose'] ?? null) === 'invest') {
            // Ready to let now beats waiting for handover; a rental listing isn't an investment buy.
            $offPlan = $property->completion_status === 'off_plan';
            $fit = $property->listing_type !== 'sale' ? 0 : ($offPlan ? 0.6 : 1);
            $add('purpose', $fit, 'Ready to rent out', $offPlan ? 'Off-plan — rental income after handover' : null);
        }

        return [
            'score' => $possible ? (int) round($earned / $possible * 100) : null,
            'reasons' => $reasons,
            'misses' => $misses,
        ];
    }

    /** Nearby place types (Master › Property Options) — value => label, cached like the chat's property-type list. */
    public static function nearbyLabels(string $lang): array
    {
        return Cache::remember("chatbot:nearby-types:{$lang}", 3600, fn () => FilterValue::query()
            ->whereHas('filter', fn ($q) => $q->where('key', NearbyPlace::FILTER_KEY))
            ->active()
            ->get()
            ->mapWithKeys(fn ($value) => [$value->value => $value->getTranslation('label', $lang) ?? $value->value])
            ->all());
    }

    /**
     * The location as typed (it may be a building or street in the address) OR any real location
     * name it means (resolveLocation: "JVC", typos, extra words) — "JVC" alone is in 2 listing
     * titles but means the 60 in Jumeirah Village Circle. Nothing recognisable keeps just the typed
     * text: an honest "no match" the relax step then widens.
     */
    private function applyLocation($query, string $location): void
    {
        $names = array_values(array_unique(array_merge([$location], $this->resolveLocation($location))));
        $query->where(function ($q) use ($names) {
            foreach ($names as $name) {
                $q->orWhere(fn ($q) => LocationFilter::apply($q, null, null, $name));
            }
        });
    }

    /** 'studio' => 0, '5+' => 5, '2' => 2, null => no preference. */
    private function rooms(?string $value): ?int
    {
        return match (true) {
            $value === null => null,
            $value === 'studio' => 0,
            default => (int) $value,
        };
    }

    /**
     * The brief as short labels ("Budget: up to AED 2,000,000", "Near: school, metro station") —
     * the agent's view on the lead's Insights panel.
     *
     * @return string[]
     */
    public static function describe(?array $brief, array $nearbyLabels = []): array
    {
        if (!$brief) {
            return [];
        }
        $aed = fn ($v) => 'AED ' . number_format((float) $v);
        $budget = match (true) {
            !empty($brief['min_price']) && !empty($brief['max_price']) => $aed($brief['min_price']) . ' – ' . $aed($brief['max_price']),
            !empty($brief['max_price']) => 'up to ' . $aed($brief['max_price']),
            !empty($brief['min_price']) => 'from ' . $aed($brief['min_price']),
            default => null,
        };

        return array_values(array_filter([
            !empty($brief['category']) ? 'Looking to: ' . ['sale' => 'Buy', 'rent' => 'Rent', 'off_plan' => 'Buy off-plan'][$brief['category']] : null,
            !empty($brief['property_type']) ? 'Type: ' . ucwords(str_replace(['_', '-'], ' ', $brief['property_type'])) : null,
            !empty($brief['location']) ? 'Location: ' . $brief['location'] : null,
            $budget ? 'Budget: ' . $budget : null,
            !empty($brief['bedrooms']) ? 'Bedrooms: ' . $brief['bedrooms'] : null,
            !empty($brief['bathrooms']) ? 'Bathrooms: ' . $brief['bathrooms'] : null,
            !empty($brief['min_area']) ? 'Min size: ' . number_format($brief['min_area']) . ' sq.ft' : null,
            !empty($brief['places']) ? 'Close to: ' . implode(', ', $brief['places']) : null,
            !empty($brief['keywords']) ? 'Wants: ' . implode(', ', $brief['keywords']) : null,
            !empty($brief['nearby']) ? 'Near: ' . implode(', ', array_map(fn ($t) => $nearbyLabels[$t] ?? str_replace('_', ' ', $t), $brief['nearby'])) : null,
            isset($brief['furnished']) ? ($brief['furnished'] ? 'Furnished' : 'Unfurnished') : null,
            !empty($brief['purpose']) ? 'Purpose: ' . ($brief['purpose'] === 'invest' ? 'Investment / rental income' : 'To live in') : null,
            !empty($brief['financing']) ? 'Financing: ' . ucfirst($brief['financing']) : null,
            !empty($brief['max_service_charge']) ? 'Service charge: up to ' . $aed($brief['max_service_charge']) . ' / year' : null,
            !empty($brief['commute']) ? 'Commute: ' . $brief['commute'] : null,
            !empty($brief['notes']) ? 'Notes: ' . $brief['notes'] : null,
        ]));
    }
}
