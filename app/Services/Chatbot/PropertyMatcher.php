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

    /** Criterion => weight (only the criteria the buyer actually gave count). */
    private const WEIGHTS = [
        'budget' => 30,
        'bedrooms' => 25,
        'nearby' => 20,
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
        $query = Property::where('status', true)->residential()
            ->with(['details', 'nearbyPlaces' => fn ($q) => $q->select('nearby_places.id', 'category')]);

        $category = $brief['category'] ?? null;
        $query->when($category === 'off_plan', fn ($q) => $q->where('completion_status', 'off_plan'))
            ->when(in_array($category, ['sale', 'rent'], true), fn ($q) => $q->where('listing_type', $category))
            // An investor buys; a rental listing can't earn them rental income.
            ->when(!$category && ($brief['purpose'] ?? null) === 'invest', fn ($q) => $q->where('listing_type', 'sale'))
            ->when($brief['property_type'] ?? null, fn ($q, $type) => $q->where('property_type', $type));
        if (!empty($brief['location'])) {
            LocationFilter::apply($query, null, null, $brief['location']);
        }

        // Loose bounds so the scored pool is the relevant end of the market, not the newest 300.
        $max = $brief['max_price'] ?? null;
        $min = $brief['min_price'] ?? null;
        $beds = $this->rooms($brief['bedrooms'] ?? null);
        $query->when($max, fn ($q) => $q->where(fn ($q) => $q->whereNull('price')->orWhere('price', '<=', $max * (1 + self::BUDGET_STRETCH))))
            ->when($min, fn ($q) => $q->where(fn ($q) => $q->whereNull('price')->orWhere('price', '>=', $min * 0.8)))
            ->when($beds !== null, fn ($q) => $q->whereBetween('bedrooms', [max(0, $beds - 1), $beds + ($brief['bedrooms'] === '5+' ? 99 : 1)]));

        $candidates = $query->orderByDesc('featured')->orderByDesc('published_at')->limit(self::CANDIDATES)->get();

        $ranked = $candidates->map(fn (Property $property) => [$property, $this->score($property, $brief, $nearbyLabels)])
            ->sortByDesc(fn ($row) => $row[1]['score'] ?? 0)
            ->take(self::RESULTS);

        return [
            'properties' => $ranked->map(fn ($row) => $this->mapProperty($row[0], $lang) + ['match' => $row[1]])->values()->all(),
            'total' => $candidates->count(),
        ];
    }

    /** @return array{score: ?int, reasons: string[], misses: string[]} score null = nothing to score on */
    private function score(Property $property, array $brief, array $nearbyLabels): array
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
