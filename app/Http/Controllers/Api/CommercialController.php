<?php

namespace App\Http\Controllers\Api;

use App\Services\CommercialPageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Public, read-only endpoint for the /commercial listing page. */
class CommercialController extends Controller
{
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
            $request->input('category'),
            $page,
        ));
    }
}
