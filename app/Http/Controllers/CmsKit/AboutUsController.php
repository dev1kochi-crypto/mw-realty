<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\SectionLabel;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use App\Support\ValidatesImageDimensions;

class AboutUsController extends Controller
{
    use ValidatesImageDimensions;

    public function index()
    {
        $section = SectionLabel::where('section_key', 'about-us')->first();
        $languages = Language::where('status', true)->get();
        $imageConfig = config('cms-kit.images.about-us.main_image');
        $ceoImageConfig = config('cms-kit.images.about-us.ceo_image');
        return view('cms-kit::about-us.index', compact('section', 'languages', 'imageConfig', 'ceoImageConfig'));
    }

    public function update(Request $request)
    {
        $languages = Language::where('status', true)->get();

        $rules = ['remove_image' => 'nullable|boolean', 'remove_ceo_image' => 'nullable|boolean'];
        foreach ($languages as $lang) {
            $rules["translations.{$lang->code}.title_1"] = 'required';
        }
        $request->validate($rules);

        $imageConfig = config('cms-kit.images.about-us.main_image', []);
        $ceoImageConfig = config('cms-kit.images.about-us.ceo_image', []);
        $this->validateImageWithinLimits($request, 'image', $imageConfig, 'About image');
        $this->validateImageWithinLimits($request, 'ceo_image', $ceoImageConfig, 'CEO image');

        $section = SectionLabel::where('section_key', 'about-us')->first();

        $data = [
            'translations' => $request->input('translations', []),
            'status' => $request->has('status'),
        ];

        if ($request->hasFile('image')) {
            if ($section?->section_image) {
                app(\App\Services\ManagedFiles::class)->delete($section->section_image);
            }
            $data['section_image'] = app(\App\Services\ManagedFiles::class)->store($request->file('image'), 'about-us');
            $data['section_image_alt'] = $request->input('image_alt');
        } elseif ($request->boolean('remove_image') && $section?->section_image) {
            app(\App\Services\ManagedFiles::class)->delete($section->section_image);
            $data['section_image'] = null;
            $data['section_image_alt'] = null;
        } elseif ($request->filled('image_alt')) {
            $data['section_image_alt'] = $request->input('image_alt');
        }

        if ($request->hasFile('ceo_image')) {
            if ($section?->banner) {
                app(\App\Services\ManagedFiles::class)->delete($section->banner);
            }
            $data['banner'] = app(\App\Services\ManagedFiles::class)->store($request->file('ceo_image'), 'about-us');
            $data['banner_alt'] = $request->input('ceo_image_alt');
        } elseif ($request->boolean('remove_ceo_image') && $section?->banner) {
            app(\App\Services\ManagedFiles::class)->delete($section->banner);
            $data['banner'] = null;
            $data['banner_alt'] = null;
        } elseif ($request->filled('ceo_image_alt')) {
            $data['banner_alt'] = $request->input('ceo_image_alt');
        }

        SectionLabel::updateOrCreate(['section_key' => 'about-us'], $data);

        return redirect()->route('cms.about-us.index')->with('success', 'About Us updated successfully.');
    }
}
