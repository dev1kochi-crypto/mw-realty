<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A Market Insights topic or region (Admin > Market Insights > Topics / Regions). Posts store the
 * term's slug in market_insights.topic / .region; `translations` is {lang: {title}}.
 */
class MarketInsightTerm extends Model
{
    public const TYPES = ['topic', 'region'];

    protected $fillable = ['type', 'slug', 'translations', 'order_index', 'status'];

    protected $casts = [
        'translations' => 'array',
        'status' => 'boolean',
    ];

    /** type => [slug => term], loaded once per request by label(). */
    private static array $cache = [];

    public function getTranslation($attribute, $lang = null)
    {
        $lang = $lang ?? app()->getLocale();

        return $this->translations[$lang][$attribute]
            ?? ($this->translations[config('app.fallback_locale')][$attribute] ?? null);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_index')->orderBy('id');
    }

    /** Translated title for a topic/region slug (falls back to English, then to the slug itself). */
    public static function label(string $type, ?string $slug, ?string $lang = null): ?string
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        self::$cache[$type] ??= static::ofType($type)->get()->keyBy('slug')->all();
        $term = self::$cache[$type][$slug] ?? null;

        return $term?->getTranslation('title', $lang) ?? $slug;
    }

    protected static function booted(): void
    {
        // Keep label() in step with admin edits made in this same request/process.
        static::saved(fn () => self::$cache = []);
        static::deleted(fn () => self::$cache = []);
    }
}
