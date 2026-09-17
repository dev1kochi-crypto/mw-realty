<?php

namespace App\Http\Controllers;

use App\Models\CmsKit\PostPropertyStep;
use App\Models\CmsKit\SectionLabel;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public read-only endpoint for the home page's "Post your property in 3 simple steps"
 * section — heading/description/button/image come from CMS > Post Property Steps'
 * section settings, the step cards are its active Steps list, in their configured order.
 */
class PublicPostPropertyStepsController extends Controller
{
    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());

        $section = SectionLabel::where('section_key', 'post-property-steps')->where('status', true)->first();
        $steps = PostPropertyStep::where('status', true)->orderBy('order_index')->get();

        return response()->json([
            'eyebrow' => $section?->getTranslation('title_1', $lang) ?: 'List with MW Realty',
            'title' => $section?->getTranslation('title', $lang) ?: 'Post your property in 3 simple steps',
            'description' => $section?->getTranslation('description', $lang) ?: null,
            'button_text' => $section?->getTranslation('button_text', $lang) ?: 'Start listing',
            'button_url' => $section?->getTranslation('button_url', $lang) ?: null,
            'image_url' => $section?->section_image ? asset('storage/' . $section->section_image) : null,
            'image_alt' => $section?->section_image_alt,
            'steps' => $steps->values()->map(fn ($step, $i) => [
                'index' => str_pad($i + 1, 2, '0', STR_PAD_LEFT),
                'icon_url' => $step->image ? asset('storage/' . $step->image) : null,
                'title' => $step->getTranslation('title', $lang),
                'description' => $step->getTranslation('description', $lang),
            ]),
        ]);
    }
}
