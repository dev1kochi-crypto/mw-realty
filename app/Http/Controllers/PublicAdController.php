<?php

namespace App\Http\Controllers;

use App\Models\CmsKit\Ad;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public read-only endpoint for a page's ad banner — returns the active ad for a given
 * placement (respecting its Active toggle and start/end date window), or null when
 * there isn't one, so the frontend section hides itself instead of showing anything stale.
 */
class PublicAdController extends Controller
{
    public function index(Request $request)
    {
        $placement = (string) $request->input('placement', 'home');
        $lang = (string) $request->input('lang', app()->getLocale());

        // Explicit `false`, not `null` — Symfony's JsonResponse turns a `null` body into
        // `{}` (an empty object), which is truthy in JS and would defeat a `v-if` check.
        return response()->json(Ad::payloadFor($placement, $lang) ?? false);
    }
}
