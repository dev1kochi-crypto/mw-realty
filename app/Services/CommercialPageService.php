<?php

namespace App\Services;

use App\Models\CmsKit\SectionLabel;
use App\Models\Property;
use App\Support\MapsPropertyCards;
use App\Support\SeoMeta;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the payload for the /commercial listing page.
 *
 * Filters by property_type (office/retail-shop/warehouse/showroom), not the `category` column —
 * `category` (residential/commercial) was seeded somewhat arbitrarily on early demo data and
 * ended up on apartment/villa listings that aren't commercial in any real sense, while
 * property_type is the actual physical building type and is what "commercial property" means
 * here in practice.
 */
class CommercialPageService
{
    use MapsPropertyCards;

    private const CACHE_TTL = 180; // seconds

    public const COMMERCIAL_TYPES = ['office', 'retail-shop', 'warehouse', 'showroom'];

    public function getListingData(
        string $lang,
        ?string $location = null,
        ?string $propertyType = null,
        ?string $search = null,
        ?string $listingCategory = null,
        int $page = 1,
        int $perPage = 6,
    ): array {
        // Location/type/search/category filtering and pagination all run in the same
        // database query — not as a client-side filter of one already-fetched page — so
        // pagination totals/pages stay correct for whatever filters are currently applied
        // (see Blogs.vue's category-filter fix earlier for why this matters).
        $cacheKey = "commercial-listing:{$lang}:{$page}:{$perPage}:"
            . ($location ?: 'all') . ':' . ($propertyType ?: 'all') . ':' . ($search ?: '') . ':' . ($listingCategory ?: 'all');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($lang, $location, $propertyType, $search, $listingCategory, $page, $perPage) {
            $section = SectionLabel::where('section_key', 'commercial')->where('status', true)->first();
            $paginator = Property::where('status', true)->whereIn('property_type', self::COMMERCIAL_TYPES)
                ->when($location, fn ($q) => $q->where(function ($q) use ($location) {
                    $q->where('translations->en->community', 'like', "%{$location}%")
                        ->orWhere('translations->ar->community', 'like', "%{$location}%");
                }))
                ->when($propertyType, fn ($q) => $q->where('property_type', $propertyType))
                ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                    $q->where('translations->en->title', 'like', "%{$search}%")
                        ->orWhere('translations->ar->title', 'like', "%{$search}%")
                        ->orWhere('translations->en->community', 'like', "%{$search}%")
                        ->orWhere('translations->ar->community', 'like', "%{$search}%");
                }))
                ->when($listingCategory === 'off_plan', fn ($q) => $q->where('completion_status', 'off_plan'))
                ->when(in_array($listingCategory, ['sale', 'rent'], true), fn ($q) => $q->where('listing_type', $listingCategory))
                ->orderByDesc('featured')
                ->orderByDesc('published_at')
                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'title' => $section?->getTranslation('title_1', $lang) ?: 'Commercial',
                'properties' => collect($paginator->items())->map(fn ($p) => $this->mapCommercialCard($p, $lang))->values(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
                'seo' => SeoMeta::forStaticPage('commercial', $lang),
            ];
        });
    }

    private function mapCommercialCard(Property $property, string $lang): array
    {
        $card = $this->mapProperty($property, $lang);
        $details = $property->details;
        $service = $property->price ? round($property->price * 0.01, -2) : 0;

        return array_merge($card, [
            'verified' => (bool) $property->featured,
            'listing_type' => $property->listing_type,
            'completion_status' => $property->completion_status,
            'cta' => $property->listing_type === 'rent' ? 'For Rent' : 'Buy Now',
            'cta_variant' => $property->listing_type === 'rent' ? 'sell' : null,
            'service' => $service ? '+' . number_format($service) . ' service' : null,
            'description' => $property->getTranslation('key_features', $lang),
            'floor' => $details?->floor,
            'rera_id' => $property->rera_id,
            'amenities' => collect($details?->amenities ?? [])->map(fn ($a) => $a['label'][$lang] ?? $a['label']['en'] ?? '')->filter()->values(),
        ]);
    }
}
