<?php

namespace App\Http\Controllers;

use App\Models\CmsKit\Banner;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public read-only endpoint the frontend's home hero section uses — content
 * comes straight from the admin-managed Banners screen, so editing the
 * headline/video/note strip there shows up here without a code change.
 */
class PublicBannerController extends Controller
{
    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());

        $banner = Banner::where('status', true)->orderBy('order_index')->first();

        if (!$banner) {
            return response()->json(null);
        }

        return response()->json([
            'banner_type' => $banner->banner_type,
            'line_1' => $banner->getTranslation('line_1', $lang),
            'line_2' => $banner->getTranslation('line_2', $lang),
            'content' => $banner->getTranslation('content', $lang),
            'image_url' => $banner->image ? asset('storage/'.$banner->image) : null,
            'image_alt' => $banner->image_alt,
            'video_url' => $banner->video_file ? asset('storage/'.$banner->video_file) : $banner->video_url,
            'note_text' => data_get($banner->translations, "{$lang}.extra_fields.note_text"),
            'note_badge' => data_get($banner->translations, "{$lang}.extra_fields.note_badge"),
            'note_link_text' => data_get($banner->translations, "{$lang}.extra_fields.note_link_text"),
            'note_link_url' => data_get($banner->translations, "{$lang}.extra_fields.note_link_url"),
        ]);
    }
}
