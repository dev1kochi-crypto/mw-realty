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
 */
class CommercialController extends Controller
{
    private const PER_PAGE = 16;

    public function __construct(private readonly CommercialPageService $commercialPage)
    {
    }

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
