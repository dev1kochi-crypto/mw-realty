<?php

namespace App\Models\CmsKit;

use App\Models\AdStat;
use App\Models\CreditTransaction;
use App\Models\PortalUser;
use App\Models\Property;
use Illuminate\Database\Eloquent\Model;

/**
 * A banner in one of the website's ad sliders. Two kinds share this table:
 *  - MW Realty's own ads (portal_user_id NULL) — CMS › Ads, always approved, optional date window.
 *  - Client ads (portal_user_id set) — booked in CRM › Advertisements for a few hours / days
 *    (App\Services\AdBookingService); they run only once an admin approves them.
 * starts_at / ends_at are date-times; ends_at is the moment the ad stops.
 */
class Ad extends Model
{
    /** Pages of the public site an ad can be placed on — matches the SPA's own routes. */
    public const PLACEMENTS = [
        'home' => 'Home',
        'commercial' => 'Commercial Properties',
        'agents' => 'Agents',
        'agent-details' => 'Agent Details',
        'agencies' => 'Agencies',
        'agency-details' => 'Agency Details',
        'blogs' => 'Blogs',
        'blog-details' => 'Blog Details',
        'properties-dubai' => 'Properties Listing',
        'premium-properties' => 'Premium Properties',
        'marketing-properties' => 'Realty (Marketing) Properties',
        'property-details' => 'Property Details (sidebar card)',
        'market-insights' => 'Market Insights',
        'terms-and-conditions' => 'Terms and Conditions',
    ];

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    public const LINK_URL = 'url';
    public const LINK_PROPERTY = 'property';
    public const LINK_PROFILE = 'profile';

    protected $fillable = [
        'portal_user_id',
        'name',
        'image',
        'image_alt',
        'mobile_image',
        'link_url',
        'link_type',
        'property_id',
        'placement',
        'starts_at',
        'ends_at',
        'duration_hours',
        'paid_with',
        'credits',
        'credit_transaction_id',
        'cancelled_at',
        'order_index',
        'status',
        'review_status',
        'review_note',
        'reviewed_at',
        'reviewed_by',
        'translations',
    ];

    protected $casts = [
        'translations' => 'array',
        'status' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'duration_hours' => 'integer',
        'credits' => 'integer',
    ];

    public function owner()
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function creditTransaction()
    {
        return $this->belongsTo(CreditTransaction::class);
    }

    public function stats()
    {
        return $this->hasMany(AdStat::class, 'ad_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeHouse($query)
    {
        return $query->whereNull('portal_user_id');
    }

    public function scopeClient($query)
    {
        return $query->whereNotNull('portal_user_id');
    }

    /** Client bookings that still hold a slot / the allowance: not rejected, not cancelled. */
    public function scopeBooked($query)
    {
        return $query->whereIn('review_status', [self::PENDING, self::APPROVED])->whereNull('cancelled_at');
    }

    /** Active, approved, not cancelled, and now falls inside its window — what the website shows. */
    public function scopeCurrentlyRunning($query)
    {
        $now = now();

        return $query->active()
            ->where('review_status', self::APPROVED)
            ->whereNull('cancelled_at')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }

    /** Bookings whose window overlaps [$from, $until). */
    public function scopeOverlapping($query, $from, $until)
    {
        return $query->where('starts_at', '<', $until)->where('ends_at', '>', $from);
    }

    public function scopeForPlacement($query, string $placement)
    {
        return $query->where('placement', $placement);
    }

    public function isClientAd(): bool
    {
        return $this->portal_user_id !== null;
    }

    /**
     * Where a client ad stands: cancelled, rejected, pending (review), scheduled, live or ended.
     * MW Realty's own ads: live, scheduled, ended or off.
     */
    public function state(): string
    {
        $now = now();

        return match (true) {
            $this->cancelled_at !== null => 'cancelled',
            $this->review_status === self::REJECTED => 'rejected',
            $this->ends_at && $this->ends_at->lte($now) => $this->review_status === self::PENDING ? 'expired' : 'ended',
            $this->review_status === self::PENDING => 'pending',
            !$this->status => 'off',
            $this->starts_at && $this->starts_at->gt($now) => 'scheduled',
            default => 'live',
        };
    }

    /**
     * $path when it's a file uploaded for an ad (the `ads` folder), else null — so replacing or
     * deleting an ad never removes an image it only points at (e.g. a listing photo used as banner).
     */
    public static function ownFile(?string $path): ?string
    {
        return $path && preg_match('#(^|/)ads/#', $path) ? $path : null;
    }

    /** Optional overlay text (eyebrow / title / text) in $lang, falling back to the default language. */
    public function getTranslation(string $field, ?string $lang = null): ?string
    {
        $lang ??= app()->getLocale();
        $value = $this->translations[$lang][$field] ?? null;

        return filled($value) ? $value : (($this->translations[config('app.fallback_locale')][$field] ?? null) ?: null);
    }

    /** The URL the ad opens: a listing, the advertiser's profile, or the typed URL. */
    public function resolvedLink(): ?string
    {
        return match ($this->link_type) {
            self::LINK_PROPERTY => $this->property?->status && $this->property->slug ? '/property-details/' . $this->property->slug : null,
            self::LINK_PROFILE => $this->owner?->slug ? ($this->owner->type === 'company' ? '/agency-details/' : '/agent-details/') . $this->owner->slug : null,
            default => $this->link_url,
        };
    }

    /** The shape every frontend ad block (and the app) reads. */
    public function payload(?string $lang = null): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image_url' => media_url($this->image),
            'mobile_image_url' => $this->mobile_image ? media_url($this->mobile_image) : null,
            'image_alt' => $this->image_alt,
            'link_url' => $this->resolvedLink(),
            'eyebrow' => $this->getTranslation('eyebrow', $lang),
            'title' => $this->getTranslation('title', $lang),
            'text' => $this->getTranslation('text', $lang),
            'sponsor' => $this->owner?->displayName(),
        ];
    }

    /**
     * Every ad running on a page, for its slider: client ads first (shuffled, so each gets the
     * first slide in turn), then MW Realty's own in their admin order.
     */
    public static function slidesFor(string $placement, ?string $lang = null): array
    {
        $running = static::forPlacement($placement)->currentlyRunning()
            ->with(['owner:id,type,name,company_name,slug', 'property:id,slug,status'])
            ->orderBy('order_index')->orderBy('id')->get();

        return $running->whereNotNull('portal_user_id')->shuffle()
            ->concat($running->whereNull('portal_user_id'))
            ->map(fn (self $ad) => $ad->payload($lang))
            ->values()->all();
    }

    /** One running ad for a page, picked at random (the older single-banner endpoint). */
    public static function payloadFor(string $placement, ?string $lang = null): ?array
    {
        $ad = static::forPlacement($placement)->currentlyRunning()->inRandomOrder()->first();

        return $ad?->payload($lang);
    }
}
