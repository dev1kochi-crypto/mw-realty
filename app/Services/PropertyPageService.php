<?php

namespace App\Services;

use App\Models\Property;
use App\Support\MapsPropertyCards;
use Illuminate\Support\Facades\Cache;

/** Builds the payload for the /property-details/{slug} page. */
class PropertyPageService
{
    use MapsPropertyCards;

    private const CACHE_TTL = 180; // seconds

    public function getPropertyDetail(string $lang, string $slug): ?array
    {
        return Cache::remember("property-detail:{$lang}:{$slug}", self::CACHE_TTL, function () use ($lang, $slug) {
            $property = Property::where('status', true)->where('slug', $slug)->with(['details', 'agent', 'owner'])->first();
            if (!$property) {
                return null;
            }

            $allImages = array_values(array_map(fn ($img) => $img['url'], $property->galleryImages()));
            if (!count($allImages)) {
                $allImages = [asset('frontend/assets/images/property-details/coming-soon.svg')];
            }

            $details = $property->details;
            $locality = $property->filterLabel('location', $lang) ?: $property->getTranslation('community', $lang);
            $city = $property->getTranslation('city', $lang);
            $location = ($locality && $city && $locality !== $city) ? "{$locality}, {$city}" : ($locality ?: $city ?: '—');

            $similar = Property::where('status', true)->where('id', '!=', $property->id)
                ->where(function ($q) use ($property) {
                    $q->where('property_type', $property->property_type)->orWhere('location', $property->location);
                })
                ->orderByDesc('featured')->orderByDesc('published_at')->take(6)->get();

            return [
                'slug' => $property->slug,
                'name' => $property->getTranslation('title', $lang),
                'description' => $property->getTranslation('description', $lang),
                'images' => $allImages,
                'location' => $location,
                'address' => $property->getTranslation('address', $lang),
                'price' => $property->price ? number_format($property->price) : null,
                'currency' => $property->currency,
                'beds' => $property->bedrooms,
                'baths' => $property->bathrooms,
                'area' => $property->sqft ? number_format($property->sqft) . ' sq.ft' : '—',
                'type' => $property->filterLabel('property_type', $lang) ?: '—',
                'listing_type' => $property->listing_type,
                'listing_type_label' => $property->listing_type === 'rent' ? 'For Rent' : 'For Sale',
                'completion_status_label' => $property->filterLabel('completion_status', $lang),
                'rera_id' => $property->rera_id,
                'reference_no' => $property->reference_no,
                'garage' => $details?->garage,
                'parking' => $details?->parking,
                'year_built' => $details?->year_built,
                'furnished' => $details?->furnished,
                'view' => $details?->view,
                'published_at' => $property->published_at?->format('F d, Y'),
                'amenities' => collect($details?->amenities ?? [])
                    ->map(fn ($a) => $a['label'][$lang] ?? $a['label']['en'] ?? '')
                    ->filter()->values(),
                'contact' => $this->mapContact($property),
                'similar' => $similar->map(fn ($p) => $this->mapProperty($p, $lang, true))->values(),
            ];
        });
    }

    /** The sidebar "listing agent" card — the assigned agent if there is one, else the owning agency, else null (card is hidden). */
    private function mapContact(Property $property): ?array
    {
        $contact = $property->agent ?: $property->owner;
        if (!$contact) {
            return null;
        }

        return [
            'type' => $contact->type,
            'slug' => $contact->slug,
            'name' => $contact->displayName(),
            'avatar_url' => $contact->avatar ? asset('storage/'.$contact->avatar) : null,
            'phone' => $contact->phone,
            'whatsapp_number' => $contact->whatsapp_number,
            'email' => $contact->email,
            'years_of_experience' => $contact->years_of_experience,
            'preferred_areas' => $contact->preferred_areas ?? [],
            'badges' => $contact->badges ?? [],
            'detail_url' => $contact->type === 'company' ? "/agency-details/{$contact->slug}" : "/agent-details/{$contact->slug}",
        ];
    }
}
