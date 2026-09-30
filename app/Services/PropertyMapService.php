<?php

namespace App\Services;

use App\Models\Property;
use App\Support\MapsPropertyCards;
use Illuminate\Support\Facades\Cache;

/**
 * Map view for /properties, /commercial and /premium-properties. Uses exactly the listing pages'
 * filters (PropertiesPageService::applyFilters), but returns compact pins for the visible map
 * area (bounding box) instead of a page of cards — so it stays fast however many listings exist.
 */
class PropertyMapService
{
    use MapsPropertyCards;

    /** Most pins returned for one map view; the frontend says "zoom in to see all" beyond this. */
    public const LIMIT = 500;

    private const CACHE_TTL = 180; // seconds

    public function __construct(private readonly PropertiesPageService $listingPage)
    {
    }

    /**
     * @param  string  $segment  residential | commercial | premium
     * @param  array  $filters  the listing page filters (see Api\PropertyMapController)
     * @param  array|null  $bbox  [south, west, north, east] or null for everything that matches
     */
    public function markers(string $lang, string $segment, array $filters, ?array $bbox): array
    {
        $refine = $this->listingPage->normaliseRefine(array_merge($filters['refine'], ['sort' => 'recent']));
        $key = 'property-map:' . md5(json_encode([$lang, $segment, $filters, $refine, $bbox]));

        return Cache::remember($key, self::CACHE_TTL, function () use ($lang, $segment, $filters, $refine, $bbox) {
            $base = fn () => $this->baseQuery($segment, $filters, $refine);

            // Extent of every matching listing (not just the visible area) — lets the map fit a
            // search like "Abu Dhabi" on first load.
            $extent = $base()->reorder()->selectRaw('MIN(latitude) as south, MIN(longitude) as west, MAX(latitude) as north, MAX(longitude) as east, COUNT(*) as total')->first();

            $inView = $base()->when($bbox, fn ($q) => $q
                ->whereBetween('latitude', [$bbox[0], $bbox[2]])
                ->whereBetween('longitude', [$bbox[1], $bbox[3]]));
            $totalInView = (clone $inView)->reorder()->count();

            $markers = $inView->with(['agent:id,phone,whatsapp_number,email', 'owner:id,phone,whatsapp_number,email', 'details'])
                ->limit(self::LIMIT)->get()
                ->map(fn (Property $p) => $this->pin($p, $lang))->values();

            return [
                'markers' => $markers,
                'total' => (int) ($extent->total ?? 0),
                'in_view' => $totalInView,
                'limit' => self::LIMIT,
                'bounds' => ($extent && $extent->total) ? [
                    'south' => (float) $extent->south, 'west' => (float) $extent->west,
                    'north' => (float) $extent->north, 'east' => (float) $extent->east,
                ] : null,
            ];
        });
    }

    private function baseQuery(string $segment, array $filters, array $refine)
    {
        $query = match ($segment) {
            'commercial' => Property::where('status', true)->commercial(),
            'premium' => Property::where('status', true)->where('featured', true),
            'marketing' => Property::where('status', true)->marketing(),
            default => $refine['agent'] || $refine['agency'] ? Property::where('status', true) : Property::where('status', true)->residential(),
        };
        $query->whereNotNull('latitude')->whereNotNull('longitude')
            ->where('latitude', '!=', 0)->where('longitude', '!=', 0);

        // /commercial's "Properties" search box: title, community or reference no, any language.
        if ($segment === 'commercial' && $filters['search']) {
            $like = '%' . mb_strtolower($filters['search']) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(reference_no) LIKE ?', [$like]);
                foreach (['en', 'ar'] as $code) {
                    foreach (['title', 'community', 'address'] as $field) {
                        $q->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(translations, '$.\"{$code}\".\"{$field}\"'))) LIKE ?", [$like]);
                    }
                }
            });
        }

        $this->listingPage->applyFilters(
            $query, $filters['location'], $filters['property_type'], $filters['purpose'],
            $filters['bedrooms'], $filters['bathrooms'], $refine,
        );

        return $query;
    }

    /** A card's fields trimmed to what a map pin + its popup card needs. */
    private function pin(Property $property, string $lang): array
    {
        $card = $this->mapProperty($property, $lang);

        return [
            'id' => $card['id'],
            'slug' => $card['slug'],
            'lat' => (float) $property->latitude,
            'lng' => (float) $property->longitude,
            'name' => $card['name'],
            'image' => $card['image'],
            'images_count' => $card['images_count'],
            'location' => $card['location'],
            'beds' => $card['beds'],
            'baths' => $card['baths'],
            'area' => $card['area'],
            'type' => $card['type'],
            'price' => $card['price'],
            'price_value' => $card['price_value'],
            'purpose_badge' => $card['purpose_badge'],
            'listing_type' => $property->listing_type,
            'segment' => $card['segment'],
            'featured' => (bool) $property->featured,
        ];
    }
}
