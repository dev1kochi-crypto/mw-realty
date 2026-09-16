<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Global, admin-managed master list of landmarks (school, hospital, restaurant, attraction, ...) —
 * the same physical place is reused across many properties, so it isn't scoped per portal owner.
 * "category" stores the FilterValue slug for the "nearby_place_type" filter (same pattern Property
 * uses for property_type/listing_type — see Filter::SELECT_KEYS is NOT involved here, that constant
 * is Property-specific; this filter is resolved directly wherever needed).
 */
class NearbyPlace extends Model
{
    public const FILTER_KEY = 'nearby_place_type';

    protected $fillable = [
        'category',
        'translations',
        'latitude',
        'longitude',
        'order_index',
        'status',
    ];

    protected $casts = [
        'translations' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'status' => 'boolean',
    ];

    public function properties()
    {
        return $this->belongsToMany(Property::class, 'property_nearby_place');
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function getTranslation($attribute, $lang = null)
    {
        $lang = $lang ?? app()->getLocale();
        return $this->translations[$lang][$attribute] ?? ($this->translations[config('app.fallback_locale')][$attribute] ?? null);
    }

    /** Resolves the "category" column (a FilterValue slug) to its language label. */
    public function typeLabel(?string $lang = null): ?string
    {
        if (!$this->category) {
            return null;
        }
        $filterId = Filter::where('key', self::FILTER_KEY)->value('id');
        $value = FilterValue::where('filter_id', $filterId)->where('value', $this->category)->first();

        return $value?->getTranslation('label', $lang) ?? $this->category;
    }
}
