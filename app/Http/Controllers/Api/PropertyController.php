<?php

namespace App\Http\Controllers\Api;

use App\Services\PropertyPageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Public, read-only endpoint for the /property-details/{slug} page. */
class PropertyController extends Controller
{
    public function __construct(private readonly PropertyPageService $propertyPage)
    {
    }

    public function show(Request $request, string $slug)
    {
        $lang = $request->input('lang', app()->getLocale());
        $data = $this->propertyPage->getPropertyDetail($lang, $slug);

        if (!$data) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($data);
    }
}
