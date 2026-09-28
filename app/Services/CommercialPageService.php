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
 * Shows listings with segment = commercial — the ones added from the CRM's Commercial menu
 * (PortalCommercialController). Not the `category` column: that was seeded somewhat arbitrarily
 * on early demo data and ended up on apartment/villa listings that aren't commercial at all.
 */
class CommercialPageService
{
    use MapsPropertyCards;

    private const CACHE_TTL = 180; // seconds

    public function getListingData(
        string $lang,
        ?string $location = null,
        ?string $propertyType = null,
        ?string $search = null,
        ?string $listingCategory = null,
        int $page = 1,
        int $perPage = 6,
        ?string $city = null,
        ?string $community = null,
        array $refine = [],
    ): array {
        // All filtering and pagination run in the same database query — not as a client-side
        // filter of one already-fetched page — so pagination totals/pages stay correct for
        // whatever filters are applied. The filters themselves are the /properties ones
        // (PropertiesPageService::applyFilters): price, area, bathrooms, completion, amenities, sort.
        $listingPage = app(PropertiesPageService::class);
        $bathrooms = in_array($refine['bathrooms'] ?? null, ['1', '2', '3', '4', '5+'], true) ? $refine['bathrooms'] : null;
        $refine = $listingPage->normaliseRefine(array_merge($refine, ['city' => $city, 'community' => $community]));
        $search = trim((string) $search) !== '' ? mb_substr(trim($search), 0, 100) : null;
        $cacheKey = "commercial-listing:{$lang}:{$page}:{$perPage}:"
            . md5(json_encode([$location, $propertyType, $search, $listingCategory, $bathrooms, $refine]));

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($lang, $location, $propertyType, $search, $listingCategory, $bathrooms, $page, $perPage, $refine, $listingPage) {
            $section = SectionLabel::where('section_key', 'commercial')->where('status', true)->first();
            $query = Property::where('status', true)->commercial()->with('details');

            // "Properties" box: title, community or reference no, any language, not case-sensitive.
            if ($search) {
                $like = '%' . mb_strtolower($search) . '%';
                $query->where(function ($q) use ($like) {
                    $q->whereRaw('LOWER(reference_no) LIKE ?', [$like]);
                    foreach (['en', 'ar'] as $code) {
                        foreach (['title', 'community', 'address'] as $field) {
                            $q->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(translations, '$.\"{$code}\".\"{$field}\"'))) LIKE ?", [$like]);
                        }
                    }
                });
            }

            $listingPage->applyFilters($query, $location, $propertyType, $listingCategory, null, $bathrooms, $refine);
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

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

        if ($refine['sort'] === 'default') {
            $data['properties'] = collect($data['properties'])->shuffle()->values()->all();
        }

        return $data;
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
            'service_value' => $service ?: null, // AED, converted on the frontend
            'description' => $property->getTranslation('key_features', $lang),
            'floor' => $details?->floor,
            'rera_id' => $property->rera_id,
            'amenities' => collect($details?->amenities ?? [])->map(fn ($a) => $a['label'][$lang] ?? $a['label']['en'] ?? '')->filter()->values(),
        ]);
    }
}
