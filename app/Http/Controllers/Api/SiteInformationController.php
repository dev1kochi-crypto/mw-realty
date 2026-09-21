<?php

namespace App\Http\Controllers\Api;

use App\Services\HomePageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Public, read-only endpoint for the site-wide footer (address/phone/email/social links). */
class SiteInformationController extends Controller
{
    public function __construct(private readonly HomePageService $homePage)
    {
    }

    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());

        return response()->json($this->homePage->getFooterData($lang));
    }
}
