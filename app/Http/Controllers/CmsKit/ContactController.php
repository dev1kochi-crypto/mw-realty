<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\SectionLabel;
use Illuminate\Routing\Controller;

/**
 * The Contact section has two variants of copy: a short one embedded on the
 * home page ("home_title"/"home_description") and the full one used on the
 * standalone Contact page ("title"/"description"). The actual form fields and
 * office address/phone/email shown alongside it come from Enquiries and Site
 * Information respectively — this only edits the section's own text.
 */
class ContactController extends Controller
{
    public function index()
    {
        $section = SectionLabel::where('section_key', 'contact-us')->first();
        $languages = Language::where('status', true)->get();
        return view('cms-kit::contact-us.index', compact('section', 'languages'));
    }

    public function update(Request $request)
    {
        $languages = Language::where('status', true)->get();
        $rules = [];
        foreach ($languages as $lang) {
            $rules["translations.{$lang->code}.home_title"] = 'required';
            $rules["translations.{$lang->code}.title"] = 'required';
        }
        $request->validate($rules);

        SectionLabel::updateOrCreate(
            ['section_key' => 'contact-us'],
            [
                'translations' => $request->input('translations', []),
                'status' => $request->has('status'),
            ]
        );

        return redirect()->route('cms.contact-us.index')->with('success', 'Contact section updated successfully.');
    }
}
