<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class SectionLabel extends Model
{
    protected $fillable = [
        'section_key',
        'translations',
        'description',
        'section_image',
        'section_image_alt',
        'banner',
        'banner_alt',
        'extra_fields',
        'status',
    ];

    protected $casts = [
        'translations' => 'array',
        'description' => 'array',
        'extra_fields' => 'array',
        'status' => 'boolean',
    ];

    // Section titles feed the cached website payloads — show an edit on the site straight away.
    protected static function booted(): void
    {
        static::saved(fn () => \App\Services\HomePageService::flushCache());
        static::deleted(fn () => \App\Services\HomePageService::flushCache());
    }

    /**
     * Helper to get translated attribute
     */
    public function getTranslation($attribute, $lang = null)
    {
        $lang = $lang ?? app()->getLocale();
        return $this->translations[$lang][$attribute] ?? ($this->translations[config('app.fallback_locale')][$attribute] ?? null);
    }
}


