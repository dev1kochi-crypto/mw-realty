<?php

namespace App\Http\Controllers\Api;

use App\Services\LegalPageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public, read-only endpoint shared by the 4 legal pages (terms/privacy/security/cookie).
 *
 * @group Content
 */
class LegalController extends Controller
{
    public function __construct(private readonly LegalPageService $legalPage)
    {
    }

    /**
     * Legal page
     *
     * @urlParam key string required terms, privacy, security or cookie. Example: privacy
     * @queryParam lang string Example: en
     *
     * @response 404 {"message": "Not found"}
     */
    public function show(Request $request, string $key)
    {
        $lang = $request->input('lang', app()->getLocale());
        $data = $this->legalPage->getPage($key, $lang);

        if (!$data) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($data);
    }
}
