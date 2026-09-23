<?php

namespace App\Support;

use App\Models\Property;

/** Shared property card shape — used anywhere a Property needs to render as a small card
 *  (home page Developments/Premium/Luxury/Realty, and Agent/Agency connected properties). */
trait MapsPropertyCards
{
    /** Card sliders only ever need a handful of photos, even when a listing has 20-50 in its
     *  full gallery — loading all of them into every card would be wasteful. The real total is
     *  still reported separately (images_count) so the photo-count badge stays accurate. */
    private const CARD_IMAGE_LIMIT = 3;

    protected function mapProperty(Property $property, string $lang, bool $shortBedBath = false): array
    {
        $allImages = array_values(array_map(fn ($img) => $img['url'], $property->galleryImages()));
        $imagesCount = count($allImages);
        // No uploaded photos yet shouldn't hide the listing — show a clearly-labelled "coming
        // soon" placeholder instead of a real generic property photo (which could be mistaken
        // for an actual photo of this listing), and report the true count (0), not the
        // placeholder array's length.
        $images = $imagesCount ? array_slice($allImages, 0, self::CARD_IMAGE_LIMIT) : [asset('frontend/assets/images/property-details/coming-soon.svg')];

        $locality = $property->filterLabel('location', $lang) ?: $property->getTranslation('community', $lang);
        $city = $property->getTranslation('city', $lang);
        $location = ($locality && $city && $locality !== $city) ? "{$locality}, {$city}" : ($locality ?: $city ?: '—');

        // "Buy | Off-Plan" — the off-plan half is only appended when it's actually off-plan;
        // "Ready" is the unremarkable default and isn't worth a badge of its own.
        $listingTypeLabel = $property->filterLabel('listing_type', $lang);
        $completionStatusLabel = $property->completion_status === 'off_plan' ? $property->filterLabel('completion_status', $lang) : null;
        $purposeBadge = implode(' | ', array_filter([$listingTypeLabel, $completionStatusLabel]));

        // Same assigned-agent-else-owning-agency fallback as the real property detail page
        // (PropertyPageService::mapContact) — kept here as raw fields (not tel:/wa.me/mailto:
        // links) so each card template decides for itself which of the three to hide when unset.
        $contact = $property->agent ?: $property->owner;

        return [
            'id' => $property->id,
            'slug' => $property->slug,
            'images' => $images,
            'images_count' => $imagesCount,
            'image' => $images[0],
            'name' => $property->getTranslation('title', $lang),
            'location' => $location,
            'beds' => $shortBedBath ? ($property->bedrooms ? "{$property->bedrooms} Bed" : '—') : $property->bedrooms,
            'baths' => $shortBedBath ? ($property->bathrooms ? "{$property->bathrooms} Bath" : '—') : $property->bathrooms,
            'area' => $property->sqft ? number_format($property->sqft) . ' sq.ft' : '—',
            'type' => $property->filterLabel('property_type', $lang) ?: '—',
            'price' => $property->price ? $property->currency . ' ' . number_format($property->price) : 'Price on request',
            'purpose_badge' => $purposeBadge ?: null,
            'furnished' => (bool) ($property->details?->furnished),
            'contact' => [
                'phone' => $contact?->phone,
                'whatsapp_number' => $contact?->whatsapp_number,
                'email' => $contact?->email,
            ],
        ];
    }
}
