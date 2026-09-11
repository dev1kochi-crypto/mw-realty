<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'translations',
        'price',
        'billing_cycle',
        'property_limit',
        'is_popular',
        'order_index',
        'status',
    ];

    protected $casts = [
        'translations' => 'array',
        'price' => 'decimal:2',
        'is_popular' => 'boolean',
        'status' => 'boolean',
    ];

    public function subscribers()
    {
        return $this->hasMany(PortalUser::class);
    }

    public function payments()
    {
        return $this->hasMany(PlanPayment::class);
    }

    public function upgradeRequests()
    {
        return $this->hasMany(PlanUpgradeRequest::class);
    }

    /** Whether this is the highest-order active plan — drives whether the Upgrade CTA still shows. */
    public function isTopTier(): bool
    {
        $maxOrderIndex = static::active()->max('order_index');

        return $maxOrderIndex !== null && (int) $this->order_index === (int) $maxOrderIndex;
    }

    public function getTranslation($attribute, $lang = null)
    {
        $lang = $lang ?? app()->getLocale();
        return $this->translations[$lang][$attribute] ?? ($this->translations[config('app.fallback_locale')][$attribute] ?? null);
    }

    public function getFeaturesAttribute()
    {
        return $this->getTranslation('features') ?? [];
    }

    public function isUnlimited(): bool
    {
        return $this->property_limit === null;
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeFree($query)
    {
        return $query->where('billing_cycle', 'free');
    }

    /**
     * New agent/company accounts default onto this plan; admin upgrades manually later.
     */
    public static function defaultFree(): ?self
    {
        return static::active()->free()->orderBy('order_index')->first();
    }
}
