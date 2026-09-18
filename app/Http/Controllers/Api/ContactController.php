<?php

namespace App\Http\Controllers\Api;

use App\Services\HomePageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Single aggregate endpoint for the standalone /contact page (GET /api/contact). */
class ContactController extends Controller
{
    public function __construct(private readonly HomePageService $homePage)
    {
    }

    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());

        return response()->json($this->homePage->getContactPageData($lang));
    }
}
