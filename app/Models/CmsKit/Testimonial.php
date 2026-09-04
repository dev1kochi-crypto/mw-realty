<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $fillable = [
        'image',
        'image_alt',
        'rating',
        'order_index',
        'status',
        'translations',
        'extra_fields',
    ];

    protected $casts = [
        'translations' => 'array',
        'extra_fields' => 'array',
        'status' => 'boolean',
    ];

    /**
     * Helper to get translated attribute
     */
    public function getTranslation($attribute, $lang = null)
    {
        $lang = $lang ?? app()->getLocale();
        return $this->translations[$lang][$attribute] ?? ($this->translations[config('app.fallback_locale')][$attribute] ?? null);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}


