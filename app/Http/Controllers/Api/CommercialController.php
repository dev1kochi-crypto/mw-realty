<?php

namespace App\Http\Controllers\Api;

use App\Services\CommercialPageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public, read-only endpoint for the /commercial listing page.
 *
 * `?search=` (title / community / ref no), `?property_type=`, `?category=sale|rent|off_plan`
 * (purpose), `?city=` / `?community=` / `?location=`, plus the same refine filters as /properties:
 * `?min_price=&max_price=`, `?min_sqft=&max_sqft=`, `?bathrooms=1..4|5+`,
 * `?completion_status=ready|off_plan`, `?amenities[]=`, `?sort=recent|price_asc|price_desc|popular`.
 *
 * @group Properties
 */
class CommercialController extends Controller
{
    private const PER_PAGE = 16;

    public function __construct(private readonly CommercialPageService $commercialPage)
    {
    }

    /**
     * Search commercial properties
     *
     * Commercial listings (16 per page) — same response shape as `GET /api/properties`.
     *
     * @queryParam lang string Example: en
     * @queryParam page integer Example: 1
     * @queryParam search string Title, community or reference number. No-example
     * @queryParam category string Purpose: sale, rent or off_plan. e.g. `rent`. No-example
     * @queryParam property_type string e.g. `office`. No-example
     * @queryParam location string No-example
     * @queryParam city string No-example
     * @queryParam community string No-example
     * @queryParam completion_status string ready or off_plan. No-example
     * @queryParam min_price number No-example
     * @queryParam max_price number No-example
     * @queryParam min_sqft number No-example
     * @queryParam max_sqft number No-example
     * @queryParam bathrooms string 1, 2, 3, 4 or 5+. No-example
     * @queryParam amenities string[] No-example
     * @queryParam sort string recent, price_asc, price_desc or popular. No-example
     */
    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());
        $page = max(1, (int) $request->input('page', 1));

        return response()->json($this->commercialPage->getListingData(
            $lang,
            $request->input('location'),
            $request->input('property_type'),
            $request->input('search'),
            $request->input('category', $request->input('listing_type')),
            $page,
            // Same page size as /properties.
            self::PER_PAGE,
            $request->input('city'),
            $request->input('community'),
            [
                'completion' => $request->input('completion_status'),
                'sort' => $request->input('sort'),
                'min_price' => $request->input('min_price'),
                'max_price' => $request->input('max_price'),
                'min_area' => $request->input('min_sqft'),
                'max_area' => $request->input('max_sqft'),
                'bathrooms' => $request->input('bathrooms'),
                'amenities' => $request->input('amenities'),
            ],
        ));
    }
}
