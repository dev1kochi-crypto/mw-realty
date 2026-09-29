<?php

namespace App\Services;

use App\Models\Property;
use App\Support\LocationFilter;
use App\Support\MapsPropertyCards;
use App\Support\SeoMeta;
use Illuminate\Support\Facades\Cache;

/** Builds the payload for the /properties listing page (all active properties, not restricted to any type). */
class PropertiesPageService
{
    use MapsPropertyCards;

    private const CACHE_TTL = 180; // seconds

    public function getListingData(
        string $lang,
        ?string $location = null,
        ?string $propertyType = null,
        ?string $category = null,
        ?string $bedrooms = null,
        ?string $bathrooms = null,
        int $page = 1,
        int $perPage = 12,
        array $refine = [],
    ): array {
        // Location/type/category/bedrooms/bathrooms filtering and pagination all run in the same
        // database query — not as a client-side filter of one already-fetched page — same
        // reasoning as CommercialPageService::getListingData().
        // $refine carries the listing page's extra filters: completion (ready|off_plan), sort,
        // min_price/max_price, min_area/max_area, amenities[] and floor_plans.
        $refine = $this->normaliseRefine($refine);
        $cacheKey = "properties-listing:{$lang}:{$page}:{$perPage}:"
            . ($location ?: 'all') . ':' . ($propertyType ?: 'all') . ':' . ($category ?: 'all')
            . ':' . ($bedrooms ?: 'any') . ':' . ($bathrooms ?: 'any') . ':' . md5(json_encode($refine))
            // Marketing list edits (add / remove / reorder) show straight away, not after the TTL.
            . ($refine['marketing'] ? ':' . \App\Models\MarketingProperty::max('updated_at') . ':' . \App\Models\MarketingProperty::count()
                . ':' . \App\Models\CmsKit\SectionLabel::where('section_key', 'home-realty-property')->value('updated_at') : '');

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($lang, $location, $propertyType, $category, $bedrooms, $bathrooms, $page, $perPage, $refine) {
            // /premium-properties: every featured ("premium") listing, residential and commercial.
            // Otherwise /properties: commercial-menu listings live on /commercial only.
            // /marketing-properties: Super Admin's hand-picked list (residential + commercial).
            $query = match (true) {
                $refine['marketing'] => Property::where('status', true)->marketing(),
                $refine['premium'] => Property::where('status', true)->where('featured', true),
                default => Property::where('status', true)->residential(),
            };
            $this->applyFilters($query, $location, $propertyType, $category, $bedrooms, $bathrooms, $refine);
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'properties' => collect($paginator->items())->map(fn ($p) => $this->mapProperty($p, $lang))->values(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
                'seo' => SeoMeta::forStaticPage($refine['marketing'] ? 'marketing-properties' : ($refine['premium'] ? 'premium-properties' : 'properties'), $lang),
                // The view-all page's heading / intro come from the same CMS section as the home
                // "Realty Property" block (Common Titles › Realty Property).
                'section' => $refine['marketing'] ? $this->marketingSection($lang) : null,
            ];
        });

        // Recommended order is shuffled for variety — except the Marketing list, which keeps the
        // order Super Admin arranged.
        if ($refine['sort'] === 'default' && !$refine['marketing']) {
            $data['properties'] = collect($data['properties'])->shuffle()->values()->all();
        }

        return $data;
    }

    /** Common Titles › Realty Property, as shown above the home section it heads. */
    private function marketingSection(string $lang): ?array
    {
        $section = \App\Models\CmsKit\SectionLabel::where('section_key', 'home-realty-property')->where('status', true)->first();

        return $section ? [
            'eyebrow' => $section->getTranslation('title_1', $lang),
            'title' => $section->getTranslation('title_2', $lang),
            'description' => $section->getTranslation('description', $lang),
        ] : null;
    }

    /**
     * Applies every listing filter plus the sort to a base query. Shared with /commercial
     * (CommercialPageService), so both pages filter the same way. $refine must already be normalised.
     */
    public function applyFilters($query, ?string $location, ?string $propertyType, ?string $category, ?string $bedrooms, ?string $bathrooms, array $refine): void
    {
        // city/community come from a picked autocomplete suggestion; $location is free text.
        LocationFilter::apply($query, $refine['city'], $refine['community'], $location);
        $query
            ->when($propertyType, fn ($q) => $q->where('property_type', $propertyType))
            ->when($category === 'off_plan', fn ($q) => $q->where('completion_status', 'off_plan'))
            ->when(in_array($category, ['sale', 'rent'], true), fn ($q) => $q->where('listing_type', $category))
            ->when($bedrooms, fn ($q) => $q->where(...$this->roomFilter('bedrooms', $bedrooms)))
            ->when($bathrooms, fn ($q) => $q->where(...$this->roomFilter('bathrooms', $bathrooms)))
            ->when($refine['completion'] === 'off_plan', fn ($q) => $q->where('completion_status', 'off_plan'))
            ->when($refine['completion'] === 'ready', fn ($q) => $q->where(fn ($q) => $q->where('completion_status', '!=', 'off_plan')->orWhereNull('completion_status')))
            ->when($refine['min_price'] !== null, fn ($q) => $q->where('price', '>=', $refine['min_price']))
            ->when($refine['max_price'] !== null, fn ($q) => $q->where('price', '<=', $refine['max_price']))
            ->when($refine['min_area'] !== null, fn ($q) => $q->where('sqft', '>=', $refine['min_area']))
            ->when($refine['max_area'] !== null, fn ($q) => $q->where('sqft', '<=', $refine['max_area']))
            ->when($refine['floor_plans'], fn ($q) => $q->has('floorPlans'));

        // Other admin select filters (Filter::SELECT_KEYS are property columns), e.g. category=commercial.
        foreach ($refine['attributes'] as $column => $value) {
            $query->where($column, $value);
        }

        foreach ($refine['amenities'] as $amenity) {
            $this->applyAmenity($query, $amenity);
        }

        match ($refine['sort']) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'popular' => $query->withCount('leads')->orderByDesc('leads_count'),
            'recent' => $query->orderByDesc('published_at'),
            // Keep database sorting cheap. Default results are shuffled after the cached page is loaded.
            default => $refine['marketing'] ?? false ? $query->marketingOrder() : $query->displayOrder()->orderByDesc('id'),
        };
    }

    /**
     * Amenity keys the listing filter panels offer → the label text each one matches. The last
     * group is for commercial listings (/commercial's panel).
     */
    private const AMENITY_LABELS = [
        'community-pool' => 'Pool',
        'gym' => 'Gym',
        'security' => 'Security',
        'balcony' => 'Balcony',
        'concierge' => 'Concierge',
        'private-pool' => 'Private Pool',
        'pantry' => 'Pantry',
        'meeting-rooms' => 'Meeting Room',
        'metro' => 'Metro',
        'loading-bay' => 'Loading',
        'central-ac' => 'Central',
    ];

    /** Whitelists and casts the optional refine filters so they're safe to query and to cache on. */
    public function normaliseRefine(array $refine): array
    {
        $number = fn ($v) => is_numeric($v) && $v >= 0 ? (float) $v : null;
        $amenities = array_values(array_intersect(
            (array) ($refine['amenities'] ?? []),
            array_merge(array_keys(self::AMENITY_LABELS), ['furnished', 'parking'])
        ));
        sort($amenities);

        return [
            'completion' => in_array($refine['completion'] ?? null, ['ready', 'off_plan'], true) ? $refine['completion'] : null,
            // 'default' = Recommended (featured, then the CRM display order); 'recent' = newest first.
            'sort' => in_array($refine['sort'] ?? null, ['default', 'recent', 'price_asc', 'price_desc', 'popular'], true) ? $refine['sort'] : 'default',
            'min_price' => $number($refine['min_price'] ?? null),
            'max_price' => $number($refine['max_price'] ?? null),
            'min_area' => $number($refine['min_area'] ?? null),
            'max_area' => $number($refine['max_area'] ?? null),
            'amenities' => $amenities,
            'floor_plans' => filter_var($refine['floor_plans'] ?? false, FILTER_VALIDATE_BOOLEAN),
            // Premium page: featured listings from Properties and Commercial (see getListingData).
            'premium' => filter_var($refine['premium'] ?? false, FILTER_VALIDATE_BOOLEAN),
            // /marketing-properties: Super Admin's Marketing Properties list, in its order.
            'marketing' => filter_var($refine['marketing'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'city' => is_string($refine['city'] ?? null) && $refine['city'] !== '' ? $refine['city'] : null,
            'community' => is_string($refine['community'] ?? null) && $refine['community'] !== '' ? $refine['community'] : null,
            // Only whitelisted column names ever reach where(); values are bound parameters.
            'attributes' => collect((array) ($refine['attributes'] ?? []))
                ->only(\App\Models\Filter::SELECT_KEYS)
                ->filter(fn ($v) => is_scalar($v) && $v !== '')
                ->map(fn ($v) => (string) $v)
                ->sortKeys()
                ->all(),
        ];
    }

    /**
     * Furnished/parking are real columns on property_details; the rest match the free-text amenity
     * labels agents enter (stored as JSON), so e.g. "gym" finds "Gym" or "Fully Equipped Gym".
     */
    private function applyAmenity($query, string $amenity): void
    {
        match ($amenity) {
            // Any furnishing option except "unfurnished" (furnished, semi-furnished, …) counts as furnished.
            'furnished' => $query->whereHas('details', fn ($q) => $q->whereNotNull('furnished')->where('furnished', '!=', \App\Models\PropertyDetail::UNFURNISHED)),
            'parking' => $query->whereHas('details', fn ($q) => $q->where('parking', '>', 0)),
            default => $query->whereHas('details', fn ($q) => $q->where('amenities', 'like', '%' . self::AMENITY_LABELS[$amenity] . '%')),
        };
    }

    /** 'studio' -> bedrooms=0, '5+' -> >=5, otherwise an exact match. Returns a [column, operator, value] where-tuple. */
    private function roomFilter(string $column, string $value): array
    {
        if ($value === 'studio') {
            return [$column, '=', 0];
        }
        if ($value === '5+') {
            return [$column, '>=', 5];
        }

        return [$column, '=', (int) $value];
    }
}
