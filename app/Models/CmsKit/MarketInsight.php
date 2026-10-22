<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A Market Insights post (report, price trend, area spotlight…). `topic` / `region` hold a
 * MarketInsightTerm slug (Admin > Market Insights > Topics / Regions); see the
 * create_market_insights migration for the shape of `translations` and `stats`.
 */
class MarketInsight extends Model
{
    protected $fillable = [
        'slug',
        'topic',
        'region',
        'published_at',
        'card_image',
        'detail_image',
        'featured_image',
        'image_alt',
        'report_file',
        'is_featured',
        'order_index',
        'status',
        'translations',
        'stats',
        'extra_fields',
        'metadata',
    ];

    protected $casts = [
        'published_at' => 'date',
        'is_featured' => 'boolean',
        'status' => 'boolean',
        'translations' => 'array',
        'stats' => 'array',
        'extra_fields' => 'array',
        'metadata' => 'array',
    ];

    public function getTranslation($attribute, $lang = null)
    {
        $lang = $lang ?? app()->getLocale();

        return $this->translations[$lang][$attribute]
            ?? ($this->translations[config('app.fallback_locale')][$attribute] ?? null);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /** Dummy SEO content generated from the post's own fields, used by SeoMeta::resolve()
     *  whenever this post's own `metadata` doesn't set a given field. */
    public function seoFallback(?string $lang = null): array
    {
        $lang = $lang ?? app()->getLocale();
        $title = $this->getTranslation('title', $lang);

        return [
            'meta_title' => $title ? "{$title} | MW Realty Market Insights" : 'MW Realty Market Insights',
            'meta_description' => \App\Support\SeoMeta::excerpt($this->getTranslation('summary', $lang) ?: $this->getTranslation('content', $lang)),
            'og_image' => ($this->detail_image ?: $this->card_image) ? media_url($this->detail_image ?: $this->card_image) : null,
        ];
    }
}
