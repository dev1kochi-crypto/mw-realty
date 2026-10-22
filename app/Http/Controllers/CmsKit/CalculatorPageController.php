<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\CalculatorPage;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\Metadata;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Admin › Calculators — the four public calculator pages. The set is fixed (each one is code), so
 * there is no create/delete: an admin edits the copy, enquiry-form copy, SEO and FAQs per language,
 * plus calculator settings (refinance lenders).
 */
class CalculatorPageController extends Controller
{
    /** Per-language SEO fields — the same set as SEO › Metadata. */
    private const SEO_FIELDS = ['canonical_url', 'meta_title', 'meta_description', 'meta_keywords', 'og_title', 'og_description', 'other_meta_tags'];

    public function index()
    {
        return view('cms-kit::calculators.index', [
            'calculators' => CalculatorPage::withCount('faqs')->orderBy('order_index')->get(),
            'lang' => config('app.fallback_locale', 'en'),
        ]);
    }

    public function edit($id)
    {
        $calculator = CalculatorPage::with('faqs')->findOrFail($id);

        return view('cms-kit::calculators.edit', [
            'calculator' => $calculator,
            'languages' => Language::where('status', true)->orderByDesc('is_default')->get(),
            'seo' => Metadata::where('page_key', $calculator->page_key)->first(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $calculator = CalculatorPage::findOrFail($id);
        $languages = Language::where('status', true)->orderByDesc('is_default')->get();
        $default = $languages->firstWhere('is_default', true)?->code ?? 'en';

        $rules = [
            "translations.{$default}.title" => 'required|string|max:120',
            "translations.{$default}.heading" => 'required|string|max:200',
            'translations.*.*' => 'nullable|string|max:2000',
            'seo.*.canonical_url' => 'nullable|url|max:500',
            'seo.*.meta_title' => 'nullable|string|max:255',
            'seo.*.meta_description' => 'nullable|string|max:500',
            'seo.*.meta_keywords' => 'nullable|string|max:500',
            'seo.*.og_title' => 'nullable|string|max:255',
            'seo.*.og_description' => 'nullable|string|max:500',
            'seo.*.other_meta_tags' => 'nullable|string|max:5000',
            'og_image' => 'nullable|image|max:512',
            'remove_og_image' => 'nullable|boolean',
            'faqs' => 'nullable|array',
            'faqs.*.*.question' => 'nullable|string|max:500',
            'faqs.*.*.answer' => 'nullable|string|max:5000',
            'faqs.*.status' => 'nullable',
            'status' => 'nullable',
        ];
        if ($calculator->key === 'refinance') {
            $rules += [
                'settings.lenders' => 'nullable|string|max:5000',
            ];
        }
        $request->validate($rules, [], [
            "translations.{$default}.title" => 'title',
            "translations.{$default}.heading" => 'heading',
        ]);

        DB::transaction(function () use ($request, $calculator, $languages, $default) {
            $translations = [];
            foreach ($languages as $lang) {
                foreach (CalculatorPage::FIELDS as $field) {
                    $translations[$lang->code][$field] = trim((string) $request->input("translations.{$lang->code}.{$field}", ''));
                }
            }

            $settings = $calculator->settings ?? [];
            if ($calculator->key === 'refinance') {
                unset($settings['rate'], $settings['max_ltv']);
                $settings['lenders'] = collect(preg_split('/\r\n|\r|\n/', (string) $request->input('settings.lenders')))
                    ->map(fn ($l) => trim($l))->filter()->unique()->values()->all();
            }

            $calculator->update([
                'translations' => $translations,
                'settings' => $settings ?: null,
                'status' => $request->boolean('status'),
            ]);

            // SEO lives in the shared metadata row, so SEO › Metadata shows the same values.
            $seo = Metadata::firstOrNew(['page_key' => $calculator->page_key]);
            $values = ['page_name' => $seo->page_name ?? []];
            foreach (self::SEO_FIELDS as $field) {
                $values[$field] = $seo->{$field} ?? [];
            }
            // SEO is edited in the default language only (as on SEO › Metadata); other languages keep what they have.
            foreach (self::SEO_FIELDS as $field) {
                $values[$field][$default] = $request->input("seo.{$default}.{$field}") ?: null;
            }
            foreach ($languages as $lang) {
                $values['page_name'][$lang->code] = $translations[$lang->code]['title'] ?: ($values['page_name'][$lang->code] ?? null);
            }
            if ($request->hasFile('og_image')) {
                if ($seo->og_image) {
                    Storage::disk('public')->delete($seo->og_image);
                }
                $values['og_image'] = $request->file('og_image')->store('metadata', 'public');
            } elseif ($request->boolean('remove_og_image') && $seo->og_image) {
                Storage::disk('public')->delete($seo->og_image);
                $values['og_image'] = null;
            }
            $seo->fill($values)->save();

            // FAQs: rows come in page order; a row needs a question and answer in the default language.
            $calculator->faqs()->delete();
            collect($request->input('faqs', []))
                ->filter(fn ($row) => filled($row[$default]['question'] ?? null) && filled($row[$default]['answer'] ?? null))
                ->values()
                ->each(function ($row, $i) use ($calculator, $languages) {
                    $calculator->faqs()->create([
                        'translations' => $languages->mapWithKeys(fn ($lang) => [$lang->code => [
                            'question' => trim((string) ($row[$lang->code]['question'] ?? '')),
                            'answer' => trim((string) ($row[$lang->code]['answer'] ?? '')),
                        ]])->all(),
                        'order_index' => $i + 1,
                        'status' => !empty($row['status']),
                    ]);
                });
        });

        foreach ($languages as $lang) {
            Cache::forget(CalculatorPage::cacheKey($lang->code));
            Cache::forget(CalculatorPage::cacheKey($lang->code, $calculator->key));
        }

        $name = $calculator->getTranslation('title', $default) ?: $calculator->key;

        return redirect()->route('cms.calculators.index')->with('success', "{$name} updated.");
    }
}
