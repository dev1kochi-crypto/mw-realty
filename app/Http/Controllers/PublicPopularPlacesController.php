<?php

namespace App\Http\Controllers;

use App\Models\CmsKit\PopularPlace;
use App\Models\CmsKit\SectionLabel;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public read-only endpoint for the home page's "Most Popular Properties Places" section —
 * heading comes from CMS > Popular Places' own section settings, the cards are its active
 * places list, in their configured order.
 */
class PublicPopularPlacesController extends Controller
{
    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());

        $section = SectionLabel::where('section_key', 'popular-places')->where('status', true)->first();
        $places = PopularPlace::where('status', true)->orderBy('order_index')->get();

        return response()->json([
            'eyebrow' => $section?->getTranslation('title_1', $lang) ?: 'In-demand cities',
            'title' => $section?->getTranslation('title', $lang) ?: 'Most Popular Properties Places',
            'places' => $places->map(fn ($place) => [
                'name' => $place->getTranslation('name', $lang),
                'image_url' => $place->image ? asset('storage/' . $place->image) : null,
                'image_alt' => $place->image_alt,
            ])->values(),
        ]);
    }
}
