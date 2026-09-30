<?php

namespace App\Http\Controllers\Api;

use App\Models\Filter;
use App\Services\PropertyMapService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public, read-only — pins for the listing pages' map view (/properties/map, /commercial/map,
 * /premium-properties/map). Takes the same query parameters as GET /api/properties and
 * /api/commercial (see those controllers), plus:
 *   `segment`  residential (default) | commercial | premium
 *   `south`, `west`, `north`, `east`  the visible map area; omitted = every matching listing.
 */
class PropertyMapController extends Controller
{
    private const PURPOSES = ['sale', 'rent', 'off_plan'];

    public function __construct(private readonly PropertyMapService $map)
    {
    }

    public function index(Request $request)
    {
        $segment = in_array($request->input('segment'), ['commercial', 'premium', 'marketing'], true) ? $request->input('segment') : 'residential';

        // Same "category means purpose when it's sale/rent/off_plan" rule as PropertiesController.
        $legacyCategory = $request->input('category');
        $purpose = $request->input('listing_type')
            ?? (in_array($legacyCategory, self::PURPOSES, true) ? $legacyCategory : null);

        $attributes = [];
        foreach (array_diff(Filter::SELECT_KEYS, ['listing_type', 'property_type', 'location', 'completion_status']) as $key) {
            $value = $request->input($key);
            if ($key === 'category' && in_array($value, self::PURPOSES, true)) {
                continue;
            }
            if (is_string($value) && $value !== '') {
                $attributes[$key] = $value;
            }
        }

        $bathrooms = $request->input('bathrooms');
        $filters = [
            'location' => $this->text($request->input('location')),
            'property_type' => $this->text($request->input('property_type')),
            'purpose' => $this->text($purpose),
            'bedrooms' => $this->text($request->input('bedrooms')),
            'bathrooms' => $segment === 'commercial' && !in_array($bathrooms, ['1', '2', '3', '4', '5+'], true) ? null : $this->text($bathrooms),
            'search' => $this->text($request->input('search')),
            'refine' => [
                'completion' => $request->input('completion_status', $request->input('completion')),
                'min_price' => $request->input('min_price'),
                'max_price' => $request->input('max_price'),
                'min_area' => $request->input('min_sqft', $request->input('min_area')),
                'max_area' => $request->input('max_sqft', $request->input('max_area')),
                'amenities' => $request->input('amenities'),
                'floor_plans' => $request->input('floor_plans'),
                'premium' => $segment === 'premium',
                'city' => $request->input('city'),
                'community' => $request->input('community'),
                'agent' => $request->input('agent'),
                'agency' => $request->input('agency'),
                'attributes' => $attributes,
            ],
        ];

        return response()->json($this->map->markers(
            $request->input('lang', app()->getLocale()),
            $segment,
            $filters,
            $this->bbox($request),
        ));
    }

    private function text($value): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 100) : null;
    }

    /** [south, west, north, east], rounded so nearby pans share a cache entry; null if absent/invalid. */
    private function bbox(Request $request): ?array
    {
        $values = array_map(fn ($k) => $request->input($k), ['south', 'west', 'north', 'east']);
        if (in_array(null, $values, true) || array_filter($values, fn ($v) => !is_numeric($v))) {
            return null;
        }
        [$south, $west, $north, $east] = array_map(fn ($v) => round((float) $v, 3), $values);
        if ($south >= $north || $west >= $east || $south < -90 || $north > 90 || $west < -180 || $east > 180) {
            return null;
        }

        return [$south, $west, $north, $east];
    }
}
