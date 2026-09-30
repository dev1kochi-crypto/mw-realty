<?php

namespace App\Services;

use App\Models\Property;
use App\Support\MapsPropertyCards;
use App\Support\SeoMeta;
use Illuminate\Support\Facades\Cache;

/** Builds the payload for the /property-details/{slug} page. */
class PropertyPageService
{
    use MapsPropertyCards;

    private const CACHE_TTL = 180; // seconds

    public function getPropertyDetail(string $lang, string $slug): ?array
    {
        $version = Property::where('slug', $slug)->value('updated_at') ?? 'missing';

        return Cache::remember("property-detail:{$lang}:{$slug}:{$version}", self::CACHE_TTL, function () use ($lang, $slug) {
            $property = Property::where('status', true)->where('slug', $slug)->with(['details', 'agent', 'owner', 'floorPlans'])->first();
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
                ->displayOrder()->take(6)->get();

            return [
                'id' => $property->id,
                'slug' => $property->slug,
                'name' => $property->getTranslation('title', $lang),
                // Rich text from the listing editor — cleaned (no scripts / styles) so it can render as HTML.
                'description' => \App\Support\SafeHtml::clean($property->getTranslation('description', $lang)),
                'images' => $allImages,
                'location' => $location,
                'address' => $property->getTranslation('address', $lang),
                'price' => $property->price ? number_format($property->price) : null,
                'price_value' => $property->price ? (float) $property->price : null, // AED, converted on the frontend
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
                'brochure_available' => (bool) $property->brochure_path,
                'floor_plan_download' => (bool) $details?->floor_plan_file, // the file itself is only given out after the lead form
                'garage' => $details?->garage,
                'parking' => $details?->parking,
                'year_built' => $details?->year_built,
                'furnished' => (bool) $details?->isFurnished(),
                'furnishing' => $details?->furnishingLabel(),
                'view' => $details?->view,
                'published_at' => $property->published_at?->format('F d, Y'),
                'amenities' => collect($details?->amenities ?? [])
                    ->map(function ($a) use ($lang) {
                        $label = $a['label'][$lang] ?? $a['label']['en'] ?? '';

                        return $label === '' ? null : [
                            'label' => $label,
                            'icon' => media_url($a['icon'] ?? null) ?: $this->amenityIcon($a['label']['en'] ?? $label),
                        ];
                    })
                    ->filter()->values(),
                'floor_plans' => $property->floorPlans->map(fn ($plan) => [
                    'label' => $plan->label,
                    'image' => media_url($plan->image),
                    'size' => $this->sizeRange($plan->size_from, $plan->size_to),
                ])->values(),
                'contact' => $this->mapContact($property),
                'similar' => $similar->map(fn ($p) => $this->mapProperty($p, $lang, true))->values(),
                'seo' => SeoMeta::resolve($property->metadata, $property->seoFallback($lang)),
            ];
        });
    }

    /** Keyword → design icon, used when an amenity row has no uploaded icon. Order matters ("parking" before "park"). */
    private const AMENITY_ICONS = [
        'pool|swim' => 'amenity-pool',
        'gym|fitness' => 'amenity-gym',
        'parking|garage' => 'amenity-parking',
        'balcony|terrace' => 'amenity-balcony',
        'cctv|camera' => 'amenity-cctv',
        'fire' => 'amenity-fire-alarm',
        'play|kids' => 'amenity-playground',
        'garden|park|landscap' => 'amenity-garden',
        'wifi|wi-fi|internet' => 'amenity-wifi',
        'security|guard|concierge' => 'amenity-security',
    ];

    private function amenityIcon(string $label): string
    {
        foreach (self::AMENITY_ICONS as $pattern => $file) {
            if (preg_match("/{$pattern}/i", $label)) {
                return asset("frontend/assets/images/property-details/{$file}.svg");
            }
        }

        return asset('frontend/assets/images/icons/verified.svg');
    }

    /** "672 to 869 sq. ft." / "672 sq. ft." / null. */
    private function sizeRange(?int $from, ?int $to): ?string
    {
        if (!$from && !$to) {
            return null;
        }
        if ($from && $to && $from !== $to) {
            return number_format($from) . ' to ' . number_format($to) . ' sq. ft.';
        }

        return number_format($from ?: $to) . ' sq. ft.';
    }

    /** The sidebar "listing agent" card — Property::displayContact(): the assigned agent while available, else the owning agency, else null (card is hidden). */
    private function mapContact(Property $property): ?array
    {
        $contact = $property->displayContact();
        if (!$contact) {
            return null;
        }

        return [
            'type' => $contact->type,
            'slug' => $contact->slug,
            'name' => $contact->displayName(),
            'avatar_url' => $contact->avatar ? media_url($contact->avatar) : null,
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
