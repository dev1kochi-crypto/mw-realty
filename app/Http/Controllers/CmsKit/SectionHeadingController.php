<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\CmsKit\Language;
use App\Models\CmsKit\SectionLabel;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * "Common Titles" — the heading/description/CTA-button content for a handful
 * of home-page sections that otherwise have no admin screen of their own.
 * Each tab is an independent single-record section (same SectionLabel model
 * already used by About Us / Luxury Projects), keyed distinctly from the
 * existing 'popular-places' / 'luxury-projects' item-list features so saving
 * here never touches their data.
 */
class SectionHeadingController extends Controller
{
    const MAX_CITIES = 6;

    protected function sections(): array
    {
        return [
            'home-developments' => ['label' => 'Developments', 'fields' => ['title_1', 'title_2', 'button_name', 'button_url', 'cities']],
            'home-premium-property' => ['label' => 'Premium Property', 'fields' => ['title_1', 'title_2', 'description', 'button_name', 'button_url']],
            'home-luxury-project' => ['label' => 'Luxury Project', 'fields' => ['title_1', 'title_2', 'description', 'button_name', 'button_url']],
            'home-realty-property' => ['label' => 'Realty Property', 'fields' => ['title_1', 'title_2', 'description', 'button_name', 'button_url']],
            // Page-hero titles for listing pages that otherwise have no admin screen of their
            // own (title_1 here is the <h1> shown on the page, not a home-page section title).
            'commercial' => ['label' => 'Commercial Page', 'fields' => ['title_1']],
            'agents' => ['label' => 'Agents Page', 'fields' => ['title_1']],
            'agencies' => ['label' => 'Agencies Page', 'fields' => ['title_1', 'title_2', 'description']],
        ];
    }

    public function index()
    {
        $languages = Language::where('status', true)->get();

        $sections = collect($this->sections())->map(fn ($config, $key) => array_merge($config, [
            'key' => $key,
            'record' => SectionLabel::where('section_key', $key)->first(),
        ]));

        return view('cms-kit::section-headings.index', compact('sections', 'languages'));
    }

    public function update(Request $request, string $section)
    {
        $sections = $this->sections();
        abort_unless(isset($sections[$section]), 404);

        $fields = $sections[$section]['fields'];
        $languages = Language::where('status', true)->get();

        // All 5 tabs' forms live on the same page and would otherwise share the exact
        // same "translations.{lang}.title_1" field names — namespacing under
        // sections.{section} keeps a failed validation's old()/errors from leaking
        // into the other four tabs when the page re-renders.
        $prefix = "sections.{$section}.translations";
        $rules = [];
        foreach ($languages as $lang) {
            $rules["{$prefix}.{$lang->code}.title_1"] = 'required|string|max:255';
            foreach (array_diff($fields, ['title_1', 'cities']) as $field) {
                $rules["{$prefix}.{$lang->code}.{$field}"] = $field === 'button_url' ? 'nullable|url|max:255' : 'nullable|string|max:255';
            }
            if (in_array('cities', $fields, true)) {
                $rules["{$prefix}.{$lang->code}.cities"] = 'nullable|array|max:'.self::MAX_CITIES;
                $rules["{$prefix}.{$lang->code}.cities.*"] = 'nullable|string|max:100';
            }
        }
        $request->validate($rules, [], ["{$prefix}.*.title_1" => 'Title 1']);

        $translations = $request->input("sections.{$section}.translations", []);
        foreach ($translations as $lang => $values) {
            $values = array_intersect_key($values, array_flip($fields));
            if (isset($values['cities'])) {
                $values['cities'] = array_slice(array_values(array_filter($values['cities'], fn ($city) => trim((string) $city) !== '')), 0, self::MAX_CITIES);
            }
            $translations[$lang] = $values;
        }

        SectionLabel::updateOrCreate(
            ['section_key' => $section],
            ['translations' => $translations, 'status' => $request->has('status')]
        );

        return redirect()->route('cms.section-headings.index')->with('success', $sections[$section]['label'].' updated successfully.');
    }
}
