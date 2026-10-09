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
 *
 * @group Home
 */
class HomeController extends Controller
{
    public function __construct(private readonly HomePageService $homePage)
    {
    }

    /**
     * Home screen
     *
     * Every home section in one request: banner, brands, ad, developments, premium properties,
     * luxury, why-choose-us, communities, popular places, testimonials, contact. Property lists
     * inside are shuffled on each call.
     *
     * @queryParam lang string Example: en
     */
    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());

        $data = $this->homePage->getHomeData($lang);
        foreach (['developments', 'premiumProperties', 'luxury', 'realty'] as $section) {
            if (isset($data[$section]['properties'])) {
                $data[$section]['properties'] = collect($data[$section]['properties'])->shuffle()->values()->all();
            }
        }

        return response()->json($data);
    }

    /**
     * New projects by city
     *
     * The home "Browse New Projects" section's listings for one city tab.
     *
     * @queryParam city string A city tab from the home response. Example: Dubai
     * @queryParam lang string Example: en
     */
    public function newProjects(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());
        $city = $request->input('city');
        $city = is_string($city) ? mb_substr($city, 0, 100) : null;

        $properties = $this->homePage->developmentProperties($lang, $city);

        return response()->json(['properties' => collect($properties)->shuffle()->values()->all()]);
    }
}
