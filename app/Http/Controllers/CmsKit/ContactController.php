<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\SectionLabel;
use Illuminate\Routing\Controller;
use App\Support\ValidatesImageDimensions;

/**
 * The Contact section has two variants of copy: a short one embedded on the
 * home page ("home_title_1"/"home_title"/"home_description") and the full one used on the
 * standalone Contact page ("title"/"description", plus its map embed URL and hero image).
 * The actual form fields and office address/phone/email/hours/social shown alongside it come
 * from Enquiries and Site Information respectively — this only edits the section's own text
 * and the two Contact-page-only assets (map, hero image).
 */
class ContactController extends Controller
{
    use ValidatesImageDimensions;

    public function index()
    {
        $section = SectionLabel::where('section_key', 'contact-us')->first();
        $languages = Language::where('status', true)->get();
        $sectionImageConfig = config('cms-kit.images.contact-us.section_image', []);
        return view('cms-kit::contact-us.index', compact('section', 'languages', 'sectionImageConfig'));
    }

    public function update(Request $request)
    {
        $languages = Language::where('status', true)->get();
        $rules = ['map_url' => 'nullable|url|max:2048', 'remove_section_image' => 'nullable|boolean'];
        foreach ($languages as $lang) {
            $rules["translations.{$lang->code}.home_title"] = 'required';
            $rules["translations.{$lang->code}.title"] = 'required';
        }
        $request->validate($rules);

        $imageConfig = config('cms-kit.images.contact-us.section_image', []);
        $this->validateImageWithinLimits($request, 'section_image', $imageConfig, 'Hero image');

        $section = SectionLabel::where('section_key', 'contact-us')->first();

        $data = [
            'translations' => $request->input('translations', []),
            'status' => $request->has('status'),
            'extra_fields' => ['map_url' => $request->input('map_url')],
        ];

        if ($request->hasFile('section_image')) {
            if ($section?->section_image) {
                app(\App\Services\ManagedFiles::class)->delete($section->section_image);
            }
            $data['section_image'] = app(\App\Services\ManagedFiles::class)->store($request->file('section_image'), 'contact-us');
            $data['section_image_alt'] = $request->input('section_image_alt');
        } elseif ($request->boolean('remove_section_image') && $section?->section_image) {
            app(\App\Services\ManagedFiles::class)->delete($section->section_image);
            $data['section_image'] = null;
            $data['section_image_alt'] = null;
        } elseif ($request->filled('section_image_alt')) {
            $data['section_image_alt'] = $request->input('section_image_alt');
        }

        SectionLabel::updateOrCreate(['section_key' => 'contact-us'], $data);

        return redirect()->route('cms.contact-us.index')->with('success', 'Contact section updated successfully.');
    }
}
