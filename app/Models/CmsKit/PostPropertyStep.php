<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class PostPropertyStep extends Model
{
    protected $fillable = [
        'image',
        'image_alt',
        'translations',
        'order_index',
        'extra_fields',
        'status',
    ];

    protected $casts = [
        'translations' => 'array',
        'extra_fields' => 'array',
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
