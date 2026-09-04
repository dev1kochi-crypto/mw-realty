<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = [
        'translations',
        'image',
        'image_alt',
        'flag',
        'flag_alt',
        'phone',
        'whatsapp',
        'fax',
        'emails',
        'country',
        'map_link',
        'order_index',
        'extra_fields',
        'status',
    ];

    protected $casts = [
        'translations' => 'array',
        'phone' => 'array',
        'emails' => 'array',
        'extra_fields' => 'array',
        'status' => 'boolean',
    ];


    /**
     * Helper to get translated attributes
     */
    public function getTranslation($attribute, $lang = null)
    {
        $lang = $lang ?? app()->getLocale();
        return $this->translations[$lang][$attribute] ?? ($this->translations[config('app.fallback_locale')][$attribute] ?? null);
    }

    /**
     * Scope for active locations
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}


