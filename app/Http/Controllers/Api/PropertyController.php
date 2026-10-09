<?php

namespace App\Http\Controllers\Api;

use App\Services\PropertyPageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public, read-only endpoint for the /property-details/{slug} page.
 *
 * @group Properties
 */
class PropertyController extends Controller
{
    public function __construct(private readonly PropertyPageService $propertyPage)
    {
    }

    /**
     * Property details
     *
     * The full listing: gallery, price, specs, description, amenities, location, agent/agency
     * contact, floor plans, open house days, similar listings and SEO. Use the property's `id`
     * from here for wishlist and lead endpoints.
     *
     * @urlParam slug string required The listing slug from a property card. Example: virella-2
     * @queryParam lang string Example: en
     *
     * @response 404 {"message": "Not found"}
     */
    public function show(Request $request, string $slug)
    {
        $lang = $request->input('lang', app()->getLocale());
        $data = $this->propertyPage->getPropertyDetail($lang, $slug);

        if (!$data) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($data);
    }
}
