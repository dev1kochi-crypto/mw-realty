<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    /** `segment` values — which CRM menu (Properties / Commercial) a listing belongs to. */
    public const SEGMENT_RESIDENTIAL = 'residential';
    public const SEGMENT_COMMERCIAL = 'commercial';

    protected $fillable = [
        'portal_user_id',
        'agent_id',
        'created_by_type',
        'created_by_id',
        'translations',
        'slug',
        'reference_no',
        'rera_id',
        'permit_number',
        'permit_expires_at',
        'permit_qr',
        'permit_verification_url',
        'permit_expiry_notified_at',
        'compliance_status',
        'compliance_submitted_at',
        'compliance_reviewed_at',
        'compliance_reviewed_by',
        'listing_type',
        'rental_period',
        'available_dates',
        'emirate',
        'permit_type',
        'permit_city',
        'permit_license_no',
        'permit_verified_at',
        'permit_verified_via',
        'permit_data',
        'completion_status',
        'property_type',
        'category',
        'segment',
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
        'brochure_path',
        'image_path',
        'image_sequence',
        'image_next_number',
        'featured',
        'featured_from',
        'featured_until',
        'status',
        'published_at',
        'order_index',
        'metadata',
        'sold_at',
        'sold_type',
        'sold_price',
        'sold_commission',
        'rented_until',
        'sold_lead_id',
        'sold_agent_id',
        'sold_notes',
        'status_before_sold',
    ];

    public const SOLD = 'sold';
    public const RENTED = 'rented';

    /**
     * `compliance_status` — the state of the listing's advertising permit, worked out automatically on
     * every save (ListingComplianceService::afterSave). A listing whose permit is verified (Validate with
     * DLD / ADREC in the property form) — or that needs no permit — is VERIFIED ("approved") and may be
     * live (`status` = true) until the permit expires. When Super Admin approval applies
     * (PermitRules::needsApproval: LISTING_SUPERADMIN_APPROVAL on, or a DTCM / None permit) it stays
     * PENDING ("Awaiting approval") until Super Admin approves it on Listing Permits.
     */
    public const COMPLIANCE_DRAFT = 'draft';                         // permit details incomplete
    public const COMPLIANCE_PENDING = 'pending';                     // permit entered, not verified / not approved yet
    public const COMPLIANCE_CHANGES_REQUESTED = 'changes_requested'; // taken down by Super Admin
    public const COMPLIANCE_APPROVED = 'approved';                   // permit verified (or none needed) — may be live
    public const COMPLIANCE_EXPIRED = 'expired';                     // permit expiry date passed

    public const COMPLIANCE_LABELS = [
        self::COMPLIANCE_DRAFT => 'Permit details needed',
        self::COMPLIANCE_PENDING => 'Not verified',
        self::COMPLIANCE_CHANGES_REQUESTED => 'Taken down',
        self::COMPLIANCE_APPROVED => 'Verified',
        self::COMPLIANCE_EXPIRED => 'Permit expired',
    ];

    protected $casts = [
        'translations' => 'array',
        'price' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'featured' => 'boolean',
        'featured_from' => 'datetime',
        'featured_until' => 'datetime',
        'status' => 'boolean',
        'published_at' => 'datetime',
        'metadata' => 'array',
        'available_dates' => 'array',
        'last_open_house_date' => 'date',
        'permit_verified_at' => 'datetime',
        'permit_data' => 'array',
        'sold_at' => 'datetime',
        'sold_price' => 'decimal:2',
        'sold_commission' => 'decimal:2',
        'rented_until' => 'date',
        'status_before_sold' => 'boolean',
        'permit_expires_at' => 'date',
        'permit_expiry_notified_at' => 'datetime',
        'compliance_submitted_at' => 'datetime',
        'compliance_reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Mirror the latest open house / viewing day into an indexed column for the "Open house" filter.
        static::saving(function (Property $property) {
            if ($property->isDirty('available_dates')) {
                $dates = array_filter((array) $property->available_dates, 'is_string');
                $property->last_open_house_date = $dates ? max($dates) : null;
            }
        });
    }

    /**
     * May this listing be switched on for the website? Approved by Super Admin, not sold, and the
     * DLD permit (when a date is recorded) hasn't expired. Every write of `status` = true checks this.
     */
    public function canGoLive(): bool
    {
        return $this->compliance_status === self::COMPLIANCE_APPROVED
            && !$this->isSold()
            && (!$this->permit_expires_at || $this->permit_expires_at->gte(today()));
    }

    public function complianceLabel(): string
    {
        if ($this->awaitingApproval()) {
            return 'Awaiting approval';
        }

        return self::COMPLIANCE_LABELS[$this->compliance_status] ?? ucfirst((string) $this->compliance_status);
    }

    /** Pending, and it's Super Admin's approval (not a permit validation) that it waits for. */
    public function awaitingApproval(): bool
    {
        return $this->compliance_status === self::COMPLIANCE_PENDING
            && \App\Support\PermitRules::needsApproval($this->permit_type);
    }

    /** Bootstrap badge classes for the compliance state (listing cards, approvals table). */
    public function complianceBadgeClass(): string
    {
        return match ($this->compliance_status) {
            self::COMPLIANCE_APPROVED => 'bg-success-subtle text-success-emphasis',
            self::COMPLIANCE_PENDING => 'bg-info-subtle text-info-emphasis',
            self::COMPLIANCE_CHANGES_REQUESTED, self::COMPLIANCE_EXPIRED => 'bg-danger-subtle text-danger-emphasis',
            default => 'bg-warning-subtle text-warning-emphasis',
        };
    }

    /** Listings that may be switched on (see canGoLive()) — used by bulk "Activate". */
    public function scopeCompliant($query)
    {
        return $query->where('properties.compliance_status', self::COMPLIANCE_APPROVED)
            ->where(fn ($q) => $q->whereNull('properties.permit_expires_at')->orWhereDate('properties.permit_expires_at', '>=', today()));
    }

    public function complianceLogs()
    {
        return $this->hasMany(PropertyComplianceLog::class)->latest('id');
    }

    /** The lead who bought / rented this listing (see PropertySaleService). */
    public function soldLead()
    {
        return $this->belongsTo(Lead::class, 'sold_lead_id');
    }

    public function soldAgent()
    {
        return $this->belongsTo(PortalUser::class, 'sold_agent_id');
    }

    public function isSold(): bool
    {
        return $this->sold_at !== null;
    }

    /** Still on the market — neither sold nor rented. */
    public function scopeAvailable($query)
    {
        return $query->whereNull('properties.sold_at');
    }

    public function scopeSoldOrRented($query)
    {
        return $query->whereNotNull('properties.sold_at');
    }

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

    /**
     * Who the public sees (and contacts) for this listing: the assigned agent while they're
     * available — active, approved, and still in the agency that owns the listing — otherwise the
     * owning agency / agent, if that account is available. Null when nobody is.
     */
    public function displayContact(): ?PortalUser
    {
        $available = fn (?PortalUser $u) => $u && $u->is_active && $u->isApproved();
        $agent = $this->agent;
        $owner = $this->owner;

        $agentAvailable = $available($agent) && (
            !$owner || $owner->type !== 'company' || $agent->id === $owner->id || $agent->company_id === $owner->id
        );

        return $agentAvailable ? $agent : ($available($owner) ? $owner : null);
    }

    public function assignmentHistory()
    {
        return $this->hasMany(PropertyAssignmentHistory::class)->latest('id');
    }

    /**
     * Listings a portal account may see and edit: its own, plus — for an agency agent — the
     * agency's listings assigned to them. Delete / reorder / feature stay owner-only.
     */
    public function scopeAccessibleBy($query, ?PortalUser $viewer)
    {
        if (!$viewer) {
            return $query;
        }

        return $query->where(function ($q) use ($viewer) {
            $q->where('properties.portal_user_id', $viewer->id);

            if ($viewer->type === 'agent' && $viewer->company_id) {
                $q->orWhere(fn ($assigned) => $assigned
                    ->where('properties.agent_id', $viewer->id)
                    ->where('properties.portal_user_id', $viewer->company_id));
            }
        });
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

    /**
     * [{number, url}] in display order, for the gallery grid. `image_path` is the gallery folder —
     * a Cloudinary folder URL (…/image/upload/MW/properties/PROP021) or a legacy local folder.
     */
    public function galleryImages(): array
    {
        if (!$this->image_path) {
            return [];
        }
        return collect($this->galleryNumbers())
            ->map(fn ($n) => ['number' => $n, 'url' => media_url(rtrim($this->image_path, '/') . '/' . $this->reference_no . '-' . $n . '.jpeg')])
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

    public function scopeSegment($query, string $segment)
    {
        return $query->where('segment', $segment);
    }

    /** The website's /properties listing — everything that isn't in the Commercial menu. */
    public function scopeResidential($query)
    {
        return $query->where('segment', '!=', self::SEGMENT_COMMERCIAL);
    }

    public function scopeCommercial($query)
    {
        return $query->where('segment', self::SEGMENT_COMMERCIAL);
    }

    /**
     * CRM listing search (Properties / Commercial pages, the Featured picker): title, address,
     * community and city in any active language, plus reference no and RERA. Not case-sensitive.
     * With $includeOwner (Super Admin), it also matches the agent / agency name.
     */
    public function scopePortalSearch($query, string $search, bool $includeOwner = false)
    {
        $like = '%' . mb_strtolower($search) . '%';
        $languages = \App\Models\CmsKit\Language::where('status', true)->pluck('code')
            ->filter(fn ($code) => preg_match('/^[A-Za-z_-]{2,10}$/', $code))
            ->push(config('app.fallback_locale', 'en'))
            ->unique();

        return $query->where(function ($q) use ($like, $languages, $includeOwner) {
            $q->whereRaw('LOWER(reference_no) LIKE ?', [$like])
                ->orWhereRaw('LOWER(rera_id) LIKE ?', [$like]);
            foreach ($languages as $code) {
                foreach (['title', 'address', 'community', 'city'] as $field) {
                    $q->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(translations, '$.\"{$code}\".\"{$field}\"'))) LIKE ?", [$like]);
                }
            }
            if ($includeOwner) {
                $q->orWhereHas('owner', fn ($o) => $o->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(company_name) LIKE ?', [$like]));
            }
        });
    }

    /**
     * The website's default listing order: exactly the CRM display order (drag and drop / "Move to"
     * on the portal Properties and Commercial pages), then newest. Premium (featured) listings get no
     * priority here — they have their own Home section and /premium-properties page.
     */
    /** Listings on Super Admin's Marketing Properties list (home "Realty Property" + /marketing-properties). */
    public function scopeMarketing($query)
    {
        return $query->whereIn('properties.id', MarketingProperty::select('property_id'));
    }

    /** The order Super Admin arranged the Marketing Properties list in (1 = first). */
    public function scopeMarketingOrder($query)
    {
        return $query->orderBy(
            MarketingProperty::select('order_index')->whereColumn('marketing_properties.property_id', 'properties.id')
        )->orderByDesc('properties.id');
    }

    public function marketingEntry()
    {
        return $this->hasOne(MarketingProperty::class);
    }

    public function scopeDisplayOrder($query)
    {
        return $query->orderBy('order_index')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    /** Has a feature booked that hasn't started yet (see FeaturedListingService). */
    public function isFeatureScheduled(): bool
    {
        return !$this->featured && $this->featured_from && $this->featured_from->isFuture();
    }

    /** Dummy SEO content generated from the property's own real fields, used by SeoMeta::resolve()
     *  whenever this property's own `metadata` doesn't set a given field. */
    public function seoFallback(?string $lang = null): array
    {
        $lang = $lang ?? app()->getLocale();
        $title = $this->getTranslation('title', $lang);
        $images = $this->galleryImages();

        return [
            'meta_title' => $title ? "{$title} | MW Realty" : 'Property | MW Realty',
            'meta_description' => \App\Support\SeoMeta::excerpt($this->getTranslation('description', $lang)) ?? sprintf(
                '%s%s in %s, offered at %s by MW Realty.',
                $this->bedrooms ? "{$this->bedrooms}-bedroom " : '',
                $this->filterLabel('property_type', $lang) ?: 'property',
                $this->getTranslation('city', $lang) ?: 'the UAE',
                $this->price ? $this->currency . ' ' . number_format($this->price) : 'a competitive price',
            ),
            'og_image' => $images[0]['url'] ?? null,
        ];
    }
}
