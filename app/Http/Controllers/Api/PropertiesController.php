<?php

namespace App\Http\Controllers\Api;

use App\Models\Filter;
use App\Services\PropertiesPageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public, read-only endpoint for the /properties listing page.
 *
 * Query parameters use the admin Filter keys (Admin > Filters), so any filter the admin enables
 * works without code changes: select filters as `?{key}=value` (listing_type, completion_status,
 * property_type, category, location, bedrooms, bathrooms) and range filters as
 * `?min_{key}=…&max_{key}=…` (price, sqft). Location: `?city=` / `?community=` (exact, from an
 * autocomplete suggestion) or `?location=` (free text). Older links are still understood: `category=sale|rent|off_plan`
 * (purpose), `completion=` and `min_area/max_area=`.
 */
class PropertiesController extends Controller
{
    private const PURPOSES = ['sale', 'rent', 'off_plan'];

    public function __construct(private readonly PropertiesPageService $propertiesPage)
    {
    }

    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());
        $page = max(1, (int) $request->input('page', 1));

        // "category" historically meant purpose (sale/rent/off_plan); as an admin filter it means
        // residential/commercial. Tell them apart by value.
        $legacyCategory = $request->input('category');
        $purpose = $request->input('listing_type')
            ?? (in_array($legacyCategory, self::PURPOSES, true) ? $legacyCategory : null);

        // Any other admin select filter whose key is a property column (e.g. category=commercial).
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

        return response()->json($this->propertiesPage->getListingData(
            $lang,
            $request->input('location'),
            $request->input('property_type'),
            $purpose,
            $request->input('bedrooms'),
            $request->input('bathrooms'),
            $page,
            16, // per page — same as /commercial (CommercialController::PER_PAGE)
            [
                'completion' => $request->input('completion_status', $request->input('completion')),
                'sort' => $request->input('sort'),
                'min_price' => $request->input('min_price'),
                'max_price' => $request->input('max_price'),
                'min_area' => $request->input('min_sqft', $request->input('min_area')),
                'max_area' => $request->input('max_sqft', $request->input('max_area')),
                'amenities' => $request->input('amenities'),
                'floor_plans' => $request->input('floor_plans'),
                // ?premium=1 — the /premium-properties page (featured listings, residential + commercial).
                'premium' => $request->input('premium'),
                // ?marketing=1 — /marketing-properties (Super Admin's Marketing Properties list).
                'marketing' => $request->input('marketing'),
                'city' => $request->input('city'),
                'community' => $request->input('community'),
                'attributes' => $attributes,
            ],
        ));
    }
}
