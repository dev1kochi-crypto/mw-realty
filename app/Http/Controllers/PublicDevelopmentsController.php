<?php

namespace App\Http\Controllers;

use App\Models\CmsKit\SectionLabel;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

/**
 * Public read-only endpoint for the home page's "Browse New Projects" section —
 * the heading/button text and the city filter tabs come from CMS > Common Titles
 * (home-developments), the 8 cards themselves are always the latest 8 active listings.
 */
class PublicDevelopmentsController extends Controller
{
    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());

        $section = SectionLabel::where('section_key', 'home-developments')->where('status', true)->first();
        $cities = $section?->getTranslation('cities', $lang);
        $cities = is_array($cities) ? array_values(array_filter($cities, fn ($c) => trim((string) $c) !== '')) : [];

        $properties = Property::where('status', true)
            ->latest('published_at')
            ->take(8)
            ->get()
            ->map(fn ($property) => $this->mapProperty($property, $lang, $cities))
            ->values();

        return response()->json([
            'eyebrow' => $section?->getTranslation('title_1', $lang) ?: 'UAE Developments',
            'title' => $section?->getTranslation('title_2', $lang) ?: 'Browse New Projects in the UAE',
            'button_name' => $section?->getTranslation('button_name', $lang) ?: 'View All Properties',
            'button_url' => $section?->getTranslation('button_url', $lang) ?: null,
            'cities' => $cities,
            'properties' => $properties,
        ]);
    }

    protected function mapProperty(Property $property, string $lang, array $cities): array
    {
        $locality = $property->filterLabel('location', $lang) ?: $property->getTranslation('community', $lang);
        $city = $property->getTranslation('city', $lang);
        $location = ($locality && $city && $locality !== $city) ? "{$locality}, {$city}" : ($locality ?: $city);

        // Bucket each listing under whichever admin-configured city tab it mentions
        // (by name, not a fixed emirate list) — "All" always shows every listing regardless.
        $haystack = Str::lower($location . ' ' . $property->getTranslation('address', $lang));
        $category = null;
        foreach ($cities as $cityName) {
            if ($cityName !== '' && Str::contains($haystack, Str::lower($cityName))) {
                $category = Str::slug($cityName);
                break;
            }
        }

        $images = array_values(array_map(fn ($img) => $img['url'], $property->galleryImages()));

        return [
            'id' => $property->id,
            'category' => $category,
            'images' => count($images) ? $images : [asset('frontend/assets/images/property-details/gallery-1.jpg')],
            'name' => $property->getTranslation('title', $lang),
            'location' => $location ?: '—',
            'beds' => $property->bedrooms,
            'baths' => $property->bathrooms,
            'area' => $property->sqft ? number_format($property->sqft) . ' sq.ft' : null,
            'type' => $property->filterLabel('property_type', $lang) ?: '—',
            'price' => $property->price ? $property->currency . ' ' . number_format($property->price) : 'Price on request',
        ];
    }
}
