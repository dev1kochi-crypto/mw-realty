<?php

namespace App\Services;

use App\Models\Property;
use App\Support\MapsPropertyCards;
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
    ): array {
        // Location/type/category/bedrooms/bathrooms filtering and pagination all run in the same
        // database query — not as a client-side filter of one already-fetched page — same
        // reasoning as CommercialPageService::getListingData().
        $cacheKey = "properties-listing:{$lang}:{$page}:{$perPage}:"
            . ($location ?: 'all') . ':' . ($propertyType ?: 'all') . ':' . ($category ?: 'all')
            . ':' . ($bedrooms ?: 'any') . ':' . ($bathrooms ?: 'any');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($lang, $location, $propertyType, $category, $bedrooms, $bathrooms, $page, $perPage) {
            $paginator = Property::where('status', true)
                ->when($location, fn ($q) => $q->where(function ($q) use ($location) {
                    $q->where('translations->en->community', 'like', "%{$location}%")
                        ->orWhere('translations->ar->community', 'like', "%{$location}%")
                        ->orWhere('translations->en->city', 'like', "%{$location}%")
                        ->orWhere('translations->ar->city', 'like', "%{$location}%");
                }))
                ->when($propertyType, fn ($q) => $q->where('property_type', $propertyType))
                ->when($category === 'off_plan', fn ($q) => $q->where('completion_status', 'off_plan'))
                ->when(in_array($category, ['sale', 'rent'], true), fn ($q) => $q->where('listing_type', $category))
                ->when($bedrooms, fn ($q) => $q->where(...$this->roomFilter('bedrooms', $bedrooms)))
                ->when($bathrooms, fn ($q) => $q->where(...$this->roomFilter('bathrooms', $bathrooms)))
                ->orderByDesc('featured')
                ->orderByDesc('published_at')
                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'properties' => collect($paginator->items())->map(fn ($p) => $this->mapProperty($p, $lang))->values(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ];
        });
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
