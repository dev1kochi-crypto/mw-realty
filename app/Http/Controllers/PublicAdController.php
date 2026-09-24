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
        $placement = $request->input('placement', 'home');

        $ad = Ad::forPlacement($placement)->currentlyRunning()->orderBy('order_index')->first();

        if (!$ad) {
            // Explicit `false`, not `null` — Symfony's JsonResponse turns a `null` body into
            // `{}` (an empty object), which is truthy in JS and would defeat a `v-if` check.
            return response()->json(false);
        }

        return response()->json([
            'name' => $ad->name,
            'image_url' => media_url($ad->image),
            'mobile_image_url' => $ad->mobile_image ? media_url($ad->mobile_image) : null,
            'image_alt' => $ad->image_alt,
            'link_url' => $ad->link_url,
        ]);
    }
}
