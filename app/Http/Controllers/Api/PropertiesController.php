<?php

namespace App\Http\Controllers\Api;

use App\Services\PropertiesPageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Public, read-only endpoint for the /properties listing page. */
class PropertiesController extends Controller
{
    public function __construct(private readonly PropertiesPageService $propertiesPage)
    {
    }

    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());
        $page = max(1, (int) $request->input('page', 1));

        return response()->json($this->propertiesPage->getListingData(
            $lang,
            $request->input('location'),
            $request->input('property_type'),
            $request->input('category'),
            $request->input('bedrooms'),
            $request->input('bathrooms'),
            $page,
        ));
    }
}
