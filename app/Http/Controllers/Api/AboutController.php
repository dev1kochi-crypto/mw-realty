<?php

namespace App\Http\Controllers\Api;

use App\Services\HomePageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Single aggregate endpoint for the standalone /about page (GET /api/about). */
class AboutController extends Controller
{
    public function __construct(private readonly HomePageService $homePage)
    {
    }

    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());

        return response()->json($this->homePage->getAboutPageData($lang));
    }
}
