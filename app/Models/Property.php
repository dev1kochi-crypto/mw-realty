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
        'listing_type',
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
