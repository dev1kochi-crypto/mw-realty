<?php

namespace App\Http\Controllers\Api;

use App\Models\CalculatorPage;
use App\Support\SeoMeta;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/**
 * Public, read-only content of the calculator pages, editable from Admin › Calculators.
 *
 * @group Content
 */
class CalculatorPageController extends Controller
{
    private const CACHE_TTL = 3600;

    /**
     * Calculators menu
     *
     * The calculators shown in the header menu and the page tabs, in order.
     *
     * @queryParam lang string Example: en
     *
     * @response {"data": [{"key": "mortgage", "title": "Mortgage Calculator"}]}
     */
    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());

        $data = Cache::remember(CalculatorPage::cacheKey($lang), self::CACHE_TTL, fn () => CalculatorPage::where('status', true)
            ->orderBy('order_index')->get()
            ->map(fn (CalculatorPage $page) => ['key' => $page->key, 'title' => $page->getTranslation('title', $lang)])
            ->values()->all());

        return response()->json(['data' => $data]);
    }

    /**
     * Calculator page
     *
     * Page copy, enquiry-form copy, FAQs, settings and SEO of one calculator.
     *
     * @urlParam key string required mortgage, eligibility, rent-vs-buy or refinance. Example: refinance
     * @queryParam lang string Example: en
     *
     * @response 404 {"message": "Not found"}
     */
    public function show(Request $request, string $key)
    {
        $lang = $request->input('lang', app()->getLocale());

        $data = Cache::remember(CalculatorPage::cacheKey($lang, $key), self::CACHE_TTL, function () use ($key, $lang) {
            $page = CalculatorPage::with(['faqs' => fn ($q) => $q->where('status', true)])->where('key', $key)->first();
            if (!$page) {
                return null;
            }
            $text = fn ($field) => $page->getTranslation($field, $lang);

            return [
                'key' => $page->key,
                'title' => $text('title'),
                'heading' => $text('heading'),
                'lead' => $text('lead'),
                'form' => [
                    'eyebrow' => $text('form_eyebrow'),
                    'title' => $text('form_title'),
                    'text' => $text('form_text'),
                    'points' => collect(preg_split('/\r\n|\r|\n/', (string) $text('form_points')))->map(fn ($p) => trim($p))->filter()->values()->all(),
                ],
                'faq_title' => $text('faq_title'),
                'faqs' => $page->faqs
                    ->map(fn ($faq) => ['question' => $faq->getTranslation('question', $lang), 'answer' => $faq->getTranslation('answer', $lang)])
                    ->filter(fn ($faq) => filled($faq['question']) && filled($faq['answer']))
                    ->values()->all(),
                'settings' => $page->settings ?? (object) [],
                'seo' => SeoMeta::forStaticPage($page->page_key, $lang),
            ];
        });

        if (!$data) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($data);
    }
}
