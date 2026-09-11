<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = [
        'portal_user_id',
        'translations',
        'slug',
        'reference_no',
        'listing_type',
        'completion_status',
        'property_type',
        'location',
        'bedrooms',
        'bathrooms',
        'sqft',
        'price',
        'currency',
        'image',
        'image_alt',
        'featured',
        'status',
    ];

    protected $casts = [
        'translations' => 'array',
        'price' => 'decimal:2',
        'featured' => 'boolean',
        'status' => 'boolean',
    ];

    public function details()
    {
        return $this->hasOne(PropertyDetail::class);
    }

    public function owner()
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function images()
    {
        return $this->hasMany(PropertyImage::class)->orderBy('order_index');
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function getTranslation($attribute, $lang = null)
    {
        $lang = $lang ?? app()->getLocale();
        return $this->translations[$lang][$attribute] ?? ($this->translations[config('app.fallback_locale')][$attribute] ?? null);
    }

    /**
     * Resolves the language label for a filter-driven column (listing_type,
     * completion_status, property_type, location) via its matching FilterValue,
     * since the raw column only stores the value slug, not a translation.
     */
    public function filterLabel(string $key, ?string $lang = null): ?string
    {
        $value = $this->{$key} ?? null;
        if (!$value) {
            return null;
        }

        $labels = app(\App\Services\PropertyLabels::class)->values();
        return ($labels[$key][$value] ?? null)?->getTranslation('label', $lang) ?? $value;
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
