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
        'featured_per_month',
        'featured_max_days',
        'featured_period',
        'agent_limit',
        'reports_access',
        'is_popular',
        'order_index',
        'status',
        'stripe_product_id',
        'stripe_price_id',
        'stripe_price_amount',
        'yearly_price',
        'stripe_yearly_price_id',
        'stripe_yearly_price_amount',
    ];

    protected $casts = [
        'translations' => 'array',
        'price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'is_popular' => 'boolean',
        'status' => 'boolean',
        'featured_per_month' => 'integer',
        'featured_max_days' => 'integer',
        'agent_limit' => 'integer',
        'reports_access' => 'boolean',
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

    public const INTERVALS = ['monthly', 'yearly'];

    public function hasYearly(): bool
    {
        return $this->yearly_price !== null && (float) $this->yearly_price > 0;
    }

    /** Price for one billing period of the given interval (`price` is the monthly price). */
    public function priceFor(string $interval = 'monthly'): float
    {
        return $interval === 'yearly' && $this->hasYearly() ? (float) $this->yearly_price : (float) $this->price;
    }

    /** How much cheaper yearly is than 12 × monthly, as a whole percentage (0 if no yearly option). */
    public function yearlySavingsPercent(): int
    {
        $twelveMonths = (float) $this->price * 12;

        return $this->hasYearly() && $twelveMonths > 0
            ? (int) round((1 - (float) $this->yearly_price / $twelveMonths) * 100)
            : 0;
    }

    public function includesFeatured(): bool
    {
        return (int) $this->featured_per_month > 0;
    }

    /** true = featured_per_month is a monthly allowance; false = how many can be featured at once. */
    public function featuredPerMonth(): bool
    {
        return $this->featured_period === 'month';
    }

    /** null = unlimited team agents, 0 = team accounts not included. */
    public function agentsUnlimited(): bool
    {
        return $this->agent_limit === null;
    }

    /**
     * The plan's enforced entitlements as pricing-card lines, generated from the real limits so
     * the card can never disagree with what's enforced: [text, included?].
     */
    public function entitlementLines(): array
    {
        $featured = $this->includesFeatured()
            ? $this->featured_per_month . ' featured listing' . ($this->featured_per_month === 1 ? '' : 's')
                . ($this->featuredPerMonth() ? ' / month' : '')
                . ($this->featured_max_days ? ' (max ' . $this->featured_max_days . ' days)' : '')
            : 'Featured listings';

        $agents = match (true) {
            $this->agentsUnlimited() => 'Unlimited team agents',
            (int) $this->agent_limit > 0 => 'Add up to ' . $this->agent_limit . ' team agent' . ($this->agent_limit === 1 ? '' : 's'),
            default => 'Team agent accounts',
        };

        return [
            [$this->isUnlimited() ? 'Unlimited listings' : $this->property_limit . ' listings', true],
            [$featured, $this->includesFeatured()],
            [$agents, $this->agentsUnlimited() || (int) $this->agent_limit > 0],
            ['Leads reports & analytics', (bool) $this->reports_access],
        ];
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
