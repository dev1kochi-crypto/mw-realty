<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'banner_type',
        'image',
        'video_url',
        'video_file',
        'image_alt',
        'order_index',
        'status',
        'translations',
        'extra_fields'
    ];

    protected $casts = [
        'translations' => 'array',
        'extra_fields' => 'array',
        'status' => 'boolean',
    ];

    /**
     * Get translated attribute
     */
    public function getTranslation($field, $lang = null)
    {
        $lang = $lang ?: app()->getLocale();
        return $this->translations[$lang][$field] ?? ($this->translations[config('app.fallback_locale')][$field] ?? null);
    }
}


