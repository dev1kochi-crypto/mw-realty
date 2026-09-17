<?php

namespace App\Http\Controllers;

use App\Models\CmsKit\SectionLabel;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public read-only endpoint for the home page's "Premium Properties" carousel —
 * heading/description/button text comes from CMS > Common Titles (home-premium-property).
 * Cards are the latest 4 properties marked Featured in the CRM (the same "Featured" toggle
 * already on the property form); if fewer than 4 are marked, the latest other listings fill
 * the rest so the section is never empty while admins are still tagging properties as premium.
 */
class PublicPremiumPropertiesController extends Controller
{
    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());
        $section = SectionLabel::where('section_key', 'home-premium-property')->where('status', true)->first();

        $featured = Property::where('status', true)->where('featured', true)->latest('published_at')->take(4)->get();
        $properties = $featured;
        if ($properties->count() < 4) {
            $filler = Property::where('status', true)
                ->whereNotIn('id', $featured->pluck('id'))
                ->latest('published_at')
                ->take(4 - $properties->count())
                ->get();
            $properties = $properties->concat($filler);
        }

        return response()->json([
            'eyebrow' => $section?->getTranslation('title_1', $lang) ?: 'Exclusive Selection',
            'title' => $section?->getTranslation('title_2', $lang) ?: 'Premium Properties',
            'description' => $section?->getTranslation('description', $lang) ?: 'A highlight of exclusive listings from our premium customers — featured homes selected for exceptional location, quality, and investment value.',
            'button_name' => $section?->getTranslation('button_name', $lang) ?: 'View More Details',
            'button_url' => $section?->getTranslation('button_url', $lang) ?: null,
            'properties' => $properties->map(fn ($property) => $this->mapProperty($property, $lang))->values(),
        ]);
    }

    protected function mapProperty(Property $property, string $lang): array
    {
        $locality = $property->filterLabel('location', $lang) ?: $property->getTranslation('community', $lang);
        $images = array_values(array_map(fn ($img) => $img['url'], $property->galleryImages()));

        return [
            'id' => $property->id,
            'images' => count($images) ? $images : [asset('frontend/assets/images/property-details/gallery-1.jpg')],
            'name' => $property->getTranslation('title', $lang),
            'location' => $locality ?: ($property->getTranslation('city', $lang) ?: '—'),
            'beds' => $property->bedrooms ? "{$property->bedrooms} Bed" : '—',
            'baths' => $property->bathrooms ? "{$property->bathrooms} Bath" : '—',
            'area' => $property->sqft ? number_format($property->sqft) . ' sq.ft' : '—',
            'price' => $property->price ? $property->currency . ' ' . number_format($property->price) : 'Price on request',
        ];
    }
}
