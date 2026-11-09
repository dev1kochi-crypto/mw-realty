<?php

namespace App\Http\Controllers\Crm\Insights;

use App\Services\MarketDemand\MarketDemandService;
use App\Services\PropertyLabels;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group CRM Market Demand
 *
 * Marketplace-wide, anonymised demand for agencies and developers: where buyers and tenants are
 * looking, which bedroom counts and price bands get more interest than there are listings for
 * (underserved), and how interest changes month by month — for any area on MW Realty, not only the
 * account's own listings. Built from aggregated activity only (MarketDemandService); no lead or
 * visitor is ever shown. Plan feature `market_demand`.
 */
class MarketDemandController extends Controller
{
    public const PURPOSES = ['sale' => 'For sale', 'rent' => 'For rent'];

    /**
     * Market demand
     *
     * The report for one area (empty = all of MW Realty) and purpose, optionally one property type:
     * interest totals and changes (last 30 days vs the 30 before), demand level against the city /
     * platform, interest vs listings by bedrooms and price band, monthly trend, most wanted
     * amenities and key findings. Counts below `fewer_than` are null.
     *
     * @queryParam area string An area id from the areas endpoint ("City|Community" or "City"). Example: Dubai|Dubai Marina
     * @queryParam purpose string sale or rent. Example: sale
     * @queryParam type string A property type value (Admin › Filters). Example: apartment
     */
    public function index(Request $request, MarketDemandService $demand, PropertyLabels $labels)
    {
        [$city, $community] = $this->area((string) $request->query('area', ''));
        $purpose = array_key_exists((string) $request->query('purpose'), self::PURPOSES) ? (string) $request->query('purpose') : 'sale';
        $types = collect($labels->values()['property_type'] ?? [])
            ->map(fn ($v, $value) => ['value' => $value, 'label' => $v->translations['en']['label'] ?? ucwords(str_replace(['-', '_'], ' ', $value))])
            ->values();
        $type = $types->contains('value', $request->query('type')) ? (string) $request->query('type') : null;

        return response()->json([
            'report' => $demand->forArea($city, $community, $purpose, $type),
            'filters' => [
                'area' => $city ? ['id' => $this->areaId($city, $community), 'name' => $community ? "{$community}, {$city}" : $city] : null,
                'purpose' => $purpose,
                'type' => $type,
            ],
            'purposes' => collect(self::PURPOSES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'types' => $types,
        ]);
    }

    /**
     * Market demand areas
     *
     * Cities and communities with live listings, for the area picker — searched and paged on the
     * server (20 per page). `?id=` returns just that area.
     *
     * @queryParam search string Example: marina
     * @queryParam page integer Example: 1
     * @queryParam id string Example: Dubai|Dubai Marina
     *
     * @response 200 {"data": [{"id": "Dubai|Dubai Marina", "name": "Dubai Marina, Dubai", "listings": 68}], "next_page": 2}
     */
    public function areas(Request $request, MarketDemandService $demand)
    {
        if ($request->filled('id')) {
            [$city, $community] = $this->area((string) $request->query('id'));

            return response()->json(['data' => $city ? [['id' => $this->areaId($city, $community), 'name' => $community ? "{$community}, {$city}" : $city]] : [], 'next_page' => null]);
        }

        $page = $demand->areas(mb_substr((string) $request->query('search', ''), 0, 100), max(1, (int) $request->query('page', 1)));

        return response()->json([
            'data' => array_map(fn ($a) => ['id' => $this->areaId($a['city'], $a['community']), 'name' => $a['label'], 'listings' => $a['listings']], $page['data']),
            'next_page' => $page['next_page'],
        ]);
    }

    /** "City|Community" / "City" → [city, community]. */
    private function area(string $id): array
    {
        $parts = array_map(fn ($p) => \App\Services\MarketDemand\DemandSegments::clean($p), explode('|', mb_substr($id, 0, 260), 2));

        return [$parts[0] ?? null, $parts[1] ?? null];
    }

    private function areaId(string $city, ?string $community): string
    {
        return $community ? "{$city}|{$community}" : $city;
    }
}
