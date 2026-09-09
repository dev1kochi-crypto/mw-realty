<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\SectionLabel;
use Illuminate\Routing\Controller;

/**
 * The "Luxury Project" home section is just a title/description/CTA button —
 * the property cards it shows are real listings (e.g. featured properties),
 * not manually managed CMS items, so this only edits the section header.
 */
class LuxuryProjectController extends Controller
{
    public function index()
    {
        $section = SectionLabel::where('section_key', 'luxury-projects')->first();
        $languages = Language::where('status', true)->get();
        return view('cms-kit::luxury-projects.index', compact('section', 'languages'));
    }

    protected function mergeTranslatableExtraFields(array $translations): array
    {
        $fieldConfig = config('cms-kit.database.luxury-projects.section.extra_fields', []);
        $translatableFields = collect($fieldConfig)->filter(fn ($field) => $field['translatable'] ?? false)->keys();

        foreach ($translations as $lang => $values) {
            $translations[$lang]['extra_fields'] = [];
            foreach ($translatableFields as $fieldName) {
                $translations[$lang]['extra_fields'][$fieldName] = data_get($values, "extra_fields.{$fieldName}");
            }
        }

        return $translations;
    }

    public function update(Request $request)
    {
        $languages = Language::where('status', true)->get();
        $sectionConfig = config('cms-kit.database.luxury-projects.section', []);
        $requiredFields = $sectionConfig['required'] ?? [];

        $rules = [];
        foreach ($languages as $lang) {
            foreach (['title', 'description'] as $field) {
                if (($sectionConfig[$field] ?? true) && in_array($field, $requiredFields)) {
                    $rules["translations.{$lang->code}.{$field}"] = 'required';
                }
            }
        }
        $request->validate($rules);

        SectionLabel::updateOrCreate(
            ['section_key' => 'luxury-projects'],
            [
                'translations' => $this->mergeTranslatableExtraFields($request->input('translations', [])),
                'status' => $request->has('status'),
            ]
        );

        return redirect()->route('cms.luxury-projects.index')->with('success', 'Section settings updated.');
    }
}
