<?php

namespace App\Http\Controllers\Api;

use App\Services\HomePageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Single aggregate endpoint for the entire home page (GET /api/home) — one request instead
 * of one-per-section, since the page always needs every section at once anyway. All of the
 * actual data-building lives in HomePageService (including its own response cache); this
 * controller is just the HTTP entry point.
 *
 * Kept OUT of this bundle (their own endpoints, reused by other pages too):
 * - /api/languages (every page's header)
 * - /api/property-filters (home hero search bar AND the properties listing page)
 * - /api/ads?placement=X (any page can ask for its own ad slot; home's is still included
 *   in this bundle too, as a convenience, so the home page needs only this one request)
 */
class HomeController extends Controller
{
    public function __construct(private readonly HomePageService $homePage)
    {
    }

    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());

        return response()->json($this->homePage->getHomeData($lang));
    }
}
