<?php

namespace App\Http\Controllers\Api;

use App\Services\AgentAgencyPageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Public, read-only endpoints for the /agencies listing page and /agency-details/{slug} page. */
class AgencyController extends Controller
{
    public function __construct(private readonly AgentAgencyPageService $agentAgencyPage)
    {
    }

    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());

        return response()->json($this->agentAgencyPage->getAgenciesListing($lang));
    }

    public function show(Request $request, string $slug)
    {
        $lang = $request->input('lang', app()->getLocale());
        $data = $this->agentAgencyPage->getAgencyDetail($lang, $slug);

        if (!$data) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($data);
    }
}
