<?php

namespace App\Http\Controllers\Api;

use App\Services\MarketInsightPageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public, read-only endpoints for the /market-insights listing page and /market-insights/{slug} page.
 *
 * @group Content
 */
class MarketInsightController extends Controller
{
    public function __construct(private readonly MarketInsightPageService $insightsPage)
    {
    }

    /**
     * List market insights
     *
     * 9 per page.
     *
     * @queryParam page integer Example: 1
     * @queryParam topic string No-example
     * @queryParam region string No-example
     * @queryParam lang string Example: en
     */
    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());
        $page = max(1, (int) $request->input('page', 1));

        return response()->json($this->insightsPage->getListingData(
            $lang,
            $page,
            9,
            $request->input('topic') ?: null,
            $request->input('region') ?: null,
        ));
    }

    /**
     * Market insight
     *
     * @urlParam slug string required Example: dubai-property-market-report-q3-2026
     * @queryParam lang string Example: en
     *
     * @response 404 {"message": "Not found"}
     */
    public function show(Request $request, string $slug)
    {
        $lang = $request->input('lang', app()->getLocale());
        $data = $this->insightsPage->getPostData($lang, $slug);

        if (!$data) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($data);
    }
}
