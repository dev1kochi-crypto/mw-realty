<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\SectionLabel;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use CMS\SiteManager\Support\ValidatesImageDimensions;

class MarketTrendController extends Controller
{
    use ValidatesImageDimensions;

    public function index()
    {
        $section = SectionLabel::where('section_key', 'market-trends')->first();
        $languages = Language::where('status', true)->get();
        $imageConfig = config('cms-kit.images.market-trends.image');
        return view('cms-kit::market-trends.index', compact('section', 'languages', 'imageConfig'));
    }

    public function update(Request $request)
    {
        $languages = Language::where('status', true)->get();

        $rules = ['remove_image_1' => 'nullable|boolean', 'remove_image_2' => 'nullable|boolean'];
        foreach ($languages as $lang) {
            $rules["translations.{$lang->code}.title"] = 'required';
        }
        $request->validate($rules);

        $imageConfig = config('cms-kit.images.market-trends.image', []);
        $this->validateImageWithinLimits($request, 'image_1', $imageConfig, 'Image 1');
        $this->validateImageWithinLimits($request, 'image_2', $imageConfig, 'Image 2');

        $section = SectionLabel::where('section_key', 'market-trends')->first();

        $data = [
            'translations' => $request->input('translations', []),
            'status' => $request->has('status'),
        ];

        if ($request->hasFile('image_1')) {
            if ($section?->section_image) {
                Storage::disk('public')->delete($section->section_image);
            }
            $data['section_image'] = $request->file('image_1')->store('market-trends', 'public');
            $data['section_image_alt'] = $request->input('image_1_alt');
        } elseif ($request->boolean('remove_image_1') && $section?->section_image) {
            Storage::disk('public')->delete($section->section_image);
            $data['section_image'] = null;
            $data['section_image_alt'] = null;
        } elseif ($request->filled('image_1_alt')) {
            $data['section_image_alt'] = $request->input('image_1_alt');
        }

        if ($request->hasFile('image_2')) {
            if ($section?->banner) {
                Storage::disk('public')->delete($section->banner);
            }
            $data['banner'] = $request->file('image_2')->store('market-trends', 'public');
            $data['banner_alt'] = $request->input('image_2_alt');
        } elseif ($request->boolean('remove_image_2') && $section?->banner) {
            Storage::disk('public')->delete($section->banner);
            $data['banner'] = null;
            $data['banner_alt'] = null;
        } elseif ($request->filled('image_2_alt')) {
            $data['banner_alt'] = $request->input('image_2_alt');
        }

        SectionLabel::updateOrCreate(['section_key' => 'market-trends'], $data);

        return redirect()->route('cms.market-trends.index')->with('success', 'Market Trends updated successfully.');
    }
}
