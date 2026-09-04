<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class Metadata extends Model
{
    protected $table = 'metadata';

    protected $fillable = [
        'page_key',
        'page_name',
        'canonical_url',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_title',
        'og_description',
        'og_image',
        'other_meta_tags',
    ];

    protected $casts = [
        'page_name' => 'array',
        'canonical_url' => 'array',
        'meta_title' => 'array',
        'meta_description' => 'array',
        'meta_keywords' => 'array',
        'og_title' => 'array',
        'og_description' => 'array',
        'other_meta_tags' => 'array',
    ];

    /**
     * Helper to get translated attribute
     */
    public function getTranslation($attribute, $lang = null)
    {
        $lang = $lang ?? app()->getLocale();
        return $this->{$attribute}[$lang] ?? ($this->{$attribute}[config('app.fallback_locale')] ?? null);
    }
}


