<?php

namespace App\Http\Controllers\Api;

use App\Services\AgentAgencyPageService;
use App\Support\PaginatesCachedList;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group Agents & Agencies
 *
 * Public, read-only endpoints for the /agents listing page and /agent-details/{slug} page.
 */
class AgentController extends Controller
{
    use PaginatesCachedList;

    public function __construct(private readonly AgentAgencyPageService $agentAgencyPage)
    {
    }

    /**
     * List agents
     *
     * Without `per_page` every agent is returned (the website's behaviour); pass `per_page` to
     * page through them and get a `pagination` block.
     *
     * @queryParam lang string Example: en
     * @queryParam per_page integer Page size, max 50. Example: 20
     * @queryParam page integer Used with per_page. Example: 1
     */
    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());

        return response()->json($this->paginateList($request, $this->agentAgencyPage->getAgentsListing($lang), 'agents'));
    }

    /**
     * Agent details
     *
     * @urlParam slug string required The agent's slug from the list. Example: sruthi-raveendran-m
     * @queryParam lang string Example: en
     *
     * @response 404 {"message": "Not found"}
     */
    public function show(Request $request, string $slug)
    {
        $lang = $request->input('lang', app()->getLocale());
        $data = $this->agentAgencyPage->getAgentDetail($lang, $slug);

        if (!$data) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($data);
    }
}
