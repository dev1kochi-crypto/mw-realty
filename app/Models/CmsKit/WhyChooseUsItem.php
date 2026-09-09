<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class WhyChooseUsItem extends Model
{
    protected $fillable = [
        'image',
        'image_alt',
        'translations',
        'order_index',
        'status',
    ];

    protected $casts = [
        'translations' => 'array',
        'status' => 'boolean',
    ];

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
