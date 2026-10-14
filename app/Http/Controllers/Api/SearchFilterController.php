<?php

namespace App\Http\Controllers\Api;

use App\Models\SearchFilter;
use App\Services\SearchFilterService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group Search & Filters
 *
 * The search filters of one listing page, as set in Admin › Filters › Search Filters. The home
 * banner search is fixed and keeps using `GET /api/property-filters?page=home`.
 */
class SearchFilterController extends Controller
{
    public function __construct(private readonly SearchFilterService $filters)
    {
    }

    /**
     * Page search filters
     *
     * Key-value: `filters` is keyed by each filter's key, and every option list is `{ value: label }`.
     * Send the picked values back to the listing endpoint under the filter's `param` (or `params.min`
     * / `params.max` for a range). Checkbox filters (`multiple: true`) take several values,
     * comma-separated. `order` / `options_order` give the admin's order.
     *
     * @urlParam page string properties, commercial, premium or offplan. Example: properties
     * @queryParam lang string Example: en
     *
     * @response {
     *   "page": "properties",
     *   "filters": {
     *     "property_type": {"label": "Property Type", "type": "checkbox", "placeholder": null, "param": "property_type", "multiple": true,
     *       "options": {"apartment": "Apartment", "villa": "Villa"}, "options_order": ["apartment", "villa"], "counts": {"apartment": 42, "villa": 17}},
     *     "bedrooms": {"label": "Bedrooms", "type": "radio", "placeholder": null, "param": "bedrooms", "multiple": false,
     *       "options": {"1": "1", "2": "2", "5+": "5+"}, "options_order": ["1", "2", "5+"]},
     *     "price": {"label": "Price", "type": "range", "placeholder": null, "min": 0, "max": 19000000, "step": 50000,
     *       "params": {"min": "min_price", "max": "max_price"}},
     *     "parking": {"label": "Parking", "type": "number", "placeholder": "Min. spaces", "min": 0, "max": 5, "step": 1, "compare": "min", "param": "min_parking"},
     *     "keyword": {"label": "Keyword", "type": "text", "placeholder": "Title or reference", "param": "keyword"}
     *   },
     *   "order": ["property_type", "bedrooms", "price", "parking", "keyword"]
     * }
     */
    public function show(Request $request, string $page)
    {
        abort_unless(array_key_exists($page, SearchFilter::PAGES), 404);
        $request->validate(['lang' => 'sometimes|string|max:10']);

        return response()->json($this->filters->payload($page, $request->input('lang', app()->getLocale())));
    }
}
