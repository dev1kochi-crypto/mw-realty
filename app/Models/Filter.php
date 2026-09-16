<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Filter extends Model
{
    public const SELECT_KEYS = ['listing_type', 'completion_status', 'property_type', 'category', 'location'];
    public const NUMBER_KEYS = ['bedrooms', 'bathrooms', 'sqft', 'price'];
    protected $fillable = [
        'key',
        'translations',
        'type',
        'show_on',
        'order_index',
        'status',
    ];

    protected $casts = [
        'translations' => 'array',
        'show_on' => 'array',
        'status' => 'boolean',
    ];

    public function values()
    {
        return $this->hasMany(FilterValue::class);
    }

    public function activeValues()
    {
        return $this->hasMany(FilterValue::class)->where('status', true)->orderBy('order_index');
    }

    public function getTranslation($attribute, $lang = null)
    {
        $lang = $lang ?? app()->getLocale();
        return $this->translations[$lang][$attribute] ?? ($this->translations[config('app.fallback_locale')][$attribute] ?? null);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeShownOn($query, string $page)
    {
        return $query->where(function ($q) use ($page) {
            $q->whereNull('show_on')->orWhereJsonContains('show_on', $page);
        });
    }
}
