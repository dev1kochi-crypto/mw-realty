<?php

namespace App\Models;

use App\Models\CmsKit\Faq;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One public calculator page (see Admin › Calculators). `key` matches the Vue route's
 * meta.calculator; the page's SEO row in `metadata` is "{key}-calculator".
 */
class CalculatorPage extends Model
{
    public const KEYS = ['mortgage', 'eligibility', 'rent-vs-buy', 'refinance'];

    /** Copy fields stored per language in `translations`. */
    public const FIELDS = ['title', 'heading', 'lead', 'form_eyebrow', 'form_title', 'form_text', 'form_points', 'faq_title'];

    protected $fillable = ['key', 'translations', 'settings', 'order_index', 'status'];

    protected $casts = [
        'translations' => 'array',
        'settings' => 'array',
        'status' => 'boolean',
    ];

    public function faqs(): MorphMany
    {
        return $this->morphMany(Faq::class, 'faqable')->orderBy('order_index');
    }

    public function getTranslation(string $attribute, ?string $lang = null): ?string
    {
        $lang = $lang ?? app()->getLocale();
        $value = $this->translations[$lang][$attribute] ?? null;

        return filled($value) ? $value : ($this->translations[config('app.fallback_locale', 'en')][$attribute] ?? null);
    }

    public function getPageKeyAttribute(): string
    {
        return "{$this->key}-calculator";
    }

    public static function cacheKey(string $lang, ?string $key = null): string
    {
        return $key ? "calculator-page:{$key}:{$lang}" : "calculator-pages:{$lang}";
    }
}
