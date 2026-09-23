<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    protected $fillable = [
        'slug',
        'published_at',
        'feature_image',
        'feature_image_alt',
        'detail_image',
        'detail_image_alt',
        'banner_image',
        'banner_alt',
        'image_3',
        'image_3_alt',
        'image_4',
        'image_4_alt',
        'order_index',
        'status',
        'translations',
        'extra_fields',
        'metadata',
    ];

    protected $casts = [
        'status' => 'boolean',
        'translations' => 'array',
        'extra_fields' => 'array',
        'metadata' => 'array',
        'published_at' => 'date',
    ];

    /**
     * Helper to get translated attribute
     */
    public function getTranslation($attribute, $lang = null)
    {
        $lang = $lang ?? app()->getLocale();
        return $this->translations[$lang][$attribute] ?? ($this->translations[config('app.fallback_locale')][$attribute] ?? null);
    }

    /** Dummy SEO content generated from the post's own real fields, used by SeoMeta::resolve()
     *  whenever this post's own `metadata` doesn't set a given field. */
    public function seoFallback(?string $lang = null): array
    {
        $lang = $lang ?? app()->getLocale();
        $title = $this->getTranslation('title', $lang);

        return [
            'meta_title' => $title ? "{$title} | MW Realty Blog" : 'MW Realty Blog',
            'meta_description' => \App\Support\SeoMeta::excerpt($this->getTranslation('content', $lang)),
            'og_image' => $this->feature_image ? asset('storage/' . $this->feature_image) : null,
        ];
    }
}


