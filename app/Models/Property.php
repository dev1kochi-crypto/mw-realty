<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = [
        'portal_user_id',
        'agent_id',
        'translations',
        'slug',
        'reference_no',
        'rera_id',
        'listing_type',
        'completion_status',
        'property_type',
        'category',
        'location',
        'postal_code',
        'latitude',
        'longitude',
        'bedrooms',
        'bathrooms',
        'sqft',
        'price',
        'currency',
        'image',
        'image_alt',
        'image_path',
        'image_sequence',
        'image_next_number',
        'featured',
        'status',
        'published_at',
        'order_index',
        'metadata',
    ];

    protected $casts = [
        'translations' => 'array',
        'price' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'featured' => 'boolean',
        'status' => 'boolean',
        'published_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function details()
    {
        return $this->hasOne(PropertyDetail::class);
    }

    public function owner()
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function agent()
    {
        return $this->belongsTo(PortalUser::class, 'agent_id');
    }

    public function images()
    {
        return $this->hasMany(PropertyImage::class)->orderBy('order_index');
    }

    /**
     * The gallery's display-ordered numbers, e.g. [1, 3, 2] — parsed from `image_sequence`
     * ("1,3,2"). Filenames are always `{reference_no}-{n}.jpeg`, so this list alone is enough to
     * both know what exists and in what order to show it; reordering only ever rewrites this.
     */
    public function galleryNumbers(): array
    {
        if (!$this->image_sequence) {
            return [];
        }
        return array_values(array_filter(array_map('intval', explode(',', $this->image_sequence))));
    }

    /** [{number, url}] in display order, for the gallery grid. */
    public function galleryImages(): array
    {
        if (!$this->image_path) {
            return [];
        }
        return collect($this->galleryNumbers())
            ->map(fn ($n) => ['number' => $n, 'url' => asset('storage/' . $this->image_path . '/' . $this->reference_no . '-' . $n . '.jpeg')])
            ->all();
    }

    public function floorPlans()
    {
        return $this->hasMany(PropertyFloorPlan::class)->orderBy('order_index');
    }

    public function nearbyPlaces()
    {
        return $this->belongsToMany(NearbyPlace::class, 'property_nearby_place');
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
