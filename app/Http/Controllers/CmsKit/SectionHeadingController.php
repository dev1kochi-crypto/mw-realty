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
            // price_range: Min/Max price (not per language, stored in extra_fields) that decide
            // which residential listings count as "luxury" (HomePageService::luxury()).
            'home-luxury-project' => ['label' => 'Luxury Project', 'fields' => ['title_1', 'title_2', 'description', 'button_name', 'button_url'], 'price_range' => true],
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
        $hasPriceRange = !empty($sections[$section]['price_range']);
        if ($hasPriceRange) {
            $rules["sections.{$section}.extra.min_price"] = 'nullable|numeric|min:0|max:9999999999999';
            $rules["sections.{$section}.extra.max_price"] = 'nullable|numeric|min:0|max:9999999999999|gte:sections.'.$section.'.extra.min_price';
        }
        $request->validate($rules, [
            "sections.{$section}.extra.max_price.gte" => 'Max price must be greater than or equal to Min price.',
        ], [
            "{$prefix}.*.title_1" => 'Title 1',
            "sections.{$section}.extra.min_price" => 'Min price',
            "sections.{$section}.extra.max_price" => 'Max price',
        ]);

        $translations = $request->input("sections.{$section}.translations", []);
        foreach ($translations as $lang => $values) {
            $values = array_intersect_key($values, array_flip($fields));
            if (isset($values['cities'])) {
                $values['cities'] = array_slice(array_values(array_filter($values['cities'], fn ($city) => trim((string) $city) !== '')), 0, self::MAX_CITIES);
            }
            $translations[$lang] = $values;
        }

        $values = ['translations' => $translations, 'status' => $request->has('status')];
        if ($hasPriceRange) {
            $existing = SectionLabel::where('section_key', $section)->value('extra_fields') ?? [];
            $existing = is_array($existing) ? $existing : (json_decode((string) $existing, true) ?: []);
            $price = fn ($key) => ($v = $request->input("sections.{$section}.extra.{$key}")) === null || $v === '' ? null : (float) $v;
            $values['extra_fields'] = array_merge($existing, ['min_price' => $price('min_price'), 'max_price' => $price('max_price')]);
        }

        SectionLabel::updateOrCreate(['section_key' => $section], $values);

        return redirect()->route('cms.section-headings.index')->with('success', $sections[$section]['label'].' updated successfully.');
    }
}
