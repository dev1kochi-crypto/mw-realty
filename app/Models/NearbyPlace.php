<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Global list of landmarks (school, hospital, restaurant, attraction, ...) — the same physical place
 * is reused across many properties, so every place is visible to and taggable by everyone; only who
 * added it (Super Admin for shared ones, else the Agent/Company in portal_user_id) can change it.
 * "category" stores the FilterValue slug for the "nearby_place_type" filter (same pattern Property
 * uses for property_type/listing_type — see Filter::SELECT_KEYS is NOT involved here, that constant
 * is Property-specific; this filter is resolved directly wherever needed).
 */
class NearbyPlace extends Model
{
    public const FILTER_KEY = 'nearby_place_type';

    /** Starter types (code => labels); Super Admin maintains the live list under Master › Property Options. */
    public const DEFAULT_TYPES = [
        'school' => ['en' => 'School', 'ar' => 'مدرسة'],
        'nursery' => ['en' => 'Nursery', 'ar' => 'حضانة'],
        'university' => ['en' => 'University', 'ar' => 'جامعة'],
        'hospital' => ['en' => 'Hospital', 'ar' => 'مستشفى'],
        'clinic' => ['en' => 'Clinic', 'ar' => 'عيادة'],
        'pharmacy' => ['en' => 'Pharmacy', 'ar' => 'صيدلية'],
        'restaurant' => ['en' => 'Restaurant', 'ar' => 'مطعم'],
        'cafe' => ['en' => 'Cafe', 'ar' => 'مقهى'],
        'shopping_mall' => ['en' => 'Shopping Mall', 'ar' => 'مركز تسوق'],
        'supermarket' => ['en' => 'Supermarket', 'ar' => 'سوبرماركت'],
        'attraction' => ['en' => 'Attraction', 'ar' => 'معلم سياحي'],
        'park' => ['en' => 'Park', 'ar' => 'حديقة'],
        'beach' => ['en' => 'Beach', 'ar' => 'شاطئ'],
        'gym' => ['en' => 'Gym', 'ar' => 'نادي رياضي'],
        'mosque' => ['en' => 'Mosque', 'ar' => 'مسجد'],
        'metro_station' => ['en' => 'Metro Station', 'ar' => 'محطة مترو'],
        'bus_stop' => ['en' => 'Bus Stop', 'ar' => 'موقف حافلات'],
        'airport' => ['en' => 'Airport', 'ar' => 'مطار'],
        'bank' => ['en' => 'Bank', 'ar' => 'بنك'],
        'hotel' => ['en' => 'Hotel', 'ar' => 'فندق'],
    ];

    protected $fillable = [
        'portal_user_id',
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

    /** The Agent/Company who added this place; null for a shared, admin-managed place. */
    public function owner()
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function isShared(): bool
    {
        return $this->portal_user_id === null;
    }

    /** Edit/toggle/delete rights: Super Admin (null $ownerId) any place, a portal user only their own. */
    public function scopeManageableBy($query, ?int $ownerId)
    {
        return $query->when($ownerId, fn ($q) => $q->where('portal_user_id', $ownerId));
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
