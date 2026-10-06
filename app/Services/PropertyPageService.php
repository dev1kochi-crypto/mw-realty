<?php

namespace App\Services;

use App\Models\Filter;
use App\Models\FilterValue;
use App\Models\NearbyPlace;
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
            $property = Property::where('status', true)->where('slug', $slug)
                ->with(['details', 'agent', 'owner', 'floorPlans', 'nearbyPlaces' => fn ($q) => $q->active()])->first();
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
                // DLD advertising permit + Madmoun QR, which Dubai rules require on every property ad.
                'permit' => $property->permit_number ? [
                    'number' => $property->permit_number,
                    'qr_url' => media_url($property->permit_qr),
                    'verify_url' => $property->permit_verification_url,
                ] : null,
                'regulatory' => $this->regulatory($property, $lang, $locality),
                'reference_no' => $property->reference_no,
                'brochure_available' => (bool) $property->brochure_path,
                'floor_plan_download' => (bool) $details?->floor_plan_file, // the file itself is only given out after the lead form
                'garage' => $details?->garage,
                'parking' => $details?->parking,
                'year_built' => $details?->year_built,
                'furnished' => (bool) $details?->isFurnished(),
                'furnishing' => $details?->furnishingLabel($lang),
                'view' => $details?->view,
                'published_at' => $property->published_at?->format('F d, Y'),
                'amenities' => $this->optionRows($details?->amenities ?? [], 'amenity', $lang),
                'easy_access' => $this->optionRows($details?->easy_access ?? [], 'easy_access', $lang),
                'attributes' => $this->optionRows($details?->property_attributes ?? [], 'property_attribute', $lang),
                // Everything else the listing form collects (unit number / owner name stay internal).
                'category_label' => $property->filterLabel('category', $lang),
                'emirate_label' => $this->optionLabel(Filter::EMIRATE_KEY, $property->emirate, $lang),
                'rental_period' => $property->listing_type === 'rent' ? $property->rental_period : null,
                'rental_period_label' => $property->listing_type === 'rent' ? $this->optionLabel(Filter::RENTAL_PERIOD_KEY, $property->rental_period, $lang) : null,
                'key_features' => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $property->getTranslation('key_features', $lang))))),
                'developer' => $details?->developer,
                'upgraded' => (bool) $details?->upgraded,
                'premium' => (bool) $property->featured,
                'direct_from_owner' => $this->directFromOwner($details?->direct_from_owner),
                'full_address' => $this->fullAddress($property, $lang),
                'floor' => $details?->floor,
                'property_age' => $details?->year_built ? max(0, now()->year - (int) $details->year_built) : null,
                'pricing' => [
                    'cheques' => $property->listing_type === 'rent' ? $details?->cheques : null,
                    'security_deposit' => $details?->security_deposit ? (float) $details->security_deposit : null,
                ],
                'availability' => $this->availability($property),
                'tours' => array_filter([
                    'video' => $this->videoEmbed($details?->video_tour_url),
                    'virtual_url' => $details?->virtual_tour_url,
                ]) ?: null,
                'floor_plans' => $property->floorPlans->map(fn ($plan) => [
                    'label' => $plan->label,
                    'image' => media_url($plan->image),
                    'size' => $this->sizeRange($plan->size_from, $plan->size_to),
                    // AED, converted to the visitor's currency on the page.
                    'price_from' => $plan->price_from ? (float) $plan->price_from : null,
                    'price_to' => $plan->price_to ? (float) $plan->price_to : null,
                ])->values(),
                'map' => $property->latitude !== null && $property->longitude !== null
                    ? ['lat' => (float) $property->latitude, 'lng' => (float) $property->longitude]
                    : null,
                'nearby' => $this->nearbyGroups($property, $lang),
                'contact' => $this->mapContact($property),
                'agency' => $this->mapAgency($property),
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

    /** Label of a Master › Property Options value (emirate, rental period …) in the page language. */
    private function optionLabel(string $filterKey, ?string $value, string $lang): ?string
    {
        if (!$value) {
            return null;
        }
        $option = FilterValue::whereHas('filter', fn ($q) => $q->where('key', $filterKey))->where('value', $value)->first();

        return $option?->getTranslation('label', $lang) ?: \Illuminate\Support\Str::headline($value);
    }

    /** "Direct from owner" is free text on the form ("Yes, owner listed"); a plain "no" means nothing to show. */
    private function directFromOwner(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || preg_match('/^(no|n|false|0|-)$/i', $value) ? null : $value;
    }

    /**
     * "Street, Community, City, Country, PO Box 12345" — the address parts that are filled in. A part the
     * street line already spells out (it often holds "JVC, Dubai, UAE") is skipped.
     */
    private function fullAddress(Property $property, string $lang): ?string
    {
        $address = '';
        foreach (['address', 'community', 'city', 'country'] as $field) {
            $part = trim((string) $property->getTranslation($field, $lang), " ,\t\n");
            if ($part !== '' && !str_contains(mb_strtolower($address), mb_strtolower($part))) {
                $address .= ($address === '' ? '' : ', ') . $part;
            }
        }
        $postal = trim((string) $property->postal_code);
        if ($postal !== '' && !preg_match('/^0+$/', $postal)) {
            $address .= ($address === '' ? '' : ', ') . "PO Box {$postal}";
        }

        return $address === '' ? null : $address;
    }

    /**
     * Available immediately, or the upcoming open house / viewing days the listing form set
     * (past dates dropped). Dates as Y-m-d — the page formats them in the visitor's language.
     */
    private function availability(Property $property): array
    {
        $dates = collect($property->available_dates ?? [])
            ->filter(fn ($d) => is_string($d) && $d >= today()->toDateString())
            ->sort()->values()->all();

        return [
            'immediate' => empty($property->available_dates),
            'dates' => $dates,
        ];
    }

    /**
     * The listing's video tour as something the page can play: a YouTube / Vimeo embed, a video file,
     * or (any other site) just a link.
     */
    private function videoEmbed(?string $url): ?array
    {
        if (!$url) {
            return null;
        }
        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([\w-]{6,})~i', $url, $m)) {
            return ['type' => 'embed', 'src' => "https://www.youtube-nocookie.com/embed/{$m[1]}?rel=0", 'url' => $url];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~i', $url, $m)) {
            return ['type' => 'embed', 'src' => "https://player.vimeo.com/video/{$m[1]}", 'url' => $url];
        }
        if (preg_match('~\.(mp4|webm|ogg)(\?.*)?$~i', $url)) {
            // A file on this site plays by path, whichever host the visitor came in on.
            $src = parse_url($url, PHP_URL_HOST) === parse_url(config('app.url'), PHP_URL_HOST) ? (parse_url($url, PHP_URL_PATH) ?: $url) : $url;

            return ['type' => 'file', 'src' => $src, 'url' => $url];
        }

        return ['type' => 'link', 'src' => null, 'url' => $url];
    }

    /**
     * "Regulatory information" on the property page: the listing's reference and advertising permit,
     * the license it's advertised under and the agent's license — what UAE rules ask a property ad to
     * show so buyers can check it. Rows without a value are left out.
     */
    private function regulatory(Property $property, string $lang, ?string $locality): array
    {
        $type = $property->permit_type;
        $license = \App\Support\PermitRules::license($type, $property->owner);
        $agency = $property->owner?->isAgent() ? ($property->owner->company ?? null) : $property->owner;

        $rows = array_filter([
            'reference' => $property->reference_no,
            'listed' => ($property->published_at ?? $property->created_at)?->locale($lang)->diffForHumans(),
            'permit_number' => $property->permit_number,
            'broker_license' => $property->permit_license_no ?: ($license['number'] ?? null),
            'agency_name' => $license['name'] ?? ($agency ? ($agency->company_name ?: $agency->name) : \App\Support\PermitRules::houseLicense()['name']),
            'zone_name' => $property->permit_data['zone_name'] ?? $locality,
            'agent_license' => $property->agent?->brn_number,
        ], fn ($v) => filled($v));

        return [
            'issuer' => \App\Support\PermitRules::issuer($type),
            'rows' => $rows,
            'qr_url' => media_url($property->permit_qr),
            'verify_url' => $property->permit_verification_url,
        ];
    }

    /**
     * Amenity rows for the page. A row picked from Master › Property Options (it has a `key`) shows
     * that option's current name and icon, so renaming an option or changing its icon shows on every
     * listing; a switched-off option still shows on listings that have it. Rows without a key keep
     * their own copy.
     */
    private function optionRows(array $rows, string $filterKey, string $lang)
    {
        $keys = collect($rows)->pluck('key')->filter()->unique();
        $options = $keys->isEmpty() ? collect() : FilterValue::whereHas('filter', fn ($q) => $q->where('key', $filterKey))
            ->whereIn('value', $keys)->get()->keyBy('value');

        return collect($rows)
            ->map(function ($row) use ($options, $lang) {
                $option = isset($row['key']) ? $options->get($row['key']) : null;
                $label = $option?->getTranslation('label', $lang) ?: ($row['label'][$lang] ?? $row['label']['en'] ?? '');
                $icon = media_url($option?->icon ?: ($row['icon'] ?? null));

                return $label === '' ? null : [
                    'label' => $label,
                    'icon' => $icon ?: $this->amenityIcon($row['label']['en'] ?? $label),
                ];
            })
            ->filter()->values();
    }

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
    /**
     * The listing's nearby places for the detail page map, grouped by type in the order Super Admin
     * set under Master › Property Options; each place carries its distance (km) from the listing.
     */
    private function nearbyGroups(Property $property, string $lang): array
    {
        $places = $property->nearbyPlaces->filter(fn ($p) => $p->latitude !== null && $p->longitude !== null);
        if ($places->isEmpty()) {
            return [];
        }

        $filterId = Filter::where('key', NearbyPlace::FILTER_KEY)->value('id');
        $types = FilterValue::where('filter_id', $filterId)->whereIn('value', $places->pluck('category')->unique())
            ->orderBy('order_index')->orderBy('id')->get()->keyBy('value');
        $hasOrigin = $property->latitude !== null && $property->longitude !== null;

        return $places
            ->groupBy('category')
            ->sortBy(fn ($group, $key) => $types->has($key) ? $types->keys()->search($key) : PHP_INT_MAX)
            ->map(fn ($group, $key) => [
                'key' => $key,
                'label' => $types->get($key)?->getTranslation('label', $lang) ?? \Illuminate\Support\Str::headline($key),
                'places' => $group->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->getTranslation('name', $lang),
                    'address' => $p->getTranslation('address', $lang),
                    'lat' => (float) $p->latitude,
                    'lng' => (float) $p->longitude,
                    'distance_km' => $hasOrigin
                        ? round($this->distanceKm((float) $property->latitude, (float) $property->longitude, (float) $p->latitude, (float) $p->longitude), 1)
                        : null,
                ])->sortBy('distance_km')->values(),
            ])
            ->values()
            ->all();
    }

    /** Great-circle distance in km (haversine). */
    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

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

        return $contact ? $this->mapProfile($contact) : null;
    }

    /**
     * The agency behind the listing's agent, shown as a second card next to the agent's: the owning
     * agency, else the agent's own agency. Null when the contact already is the agency, the agent is
     * independent, or the agency isn't available (active and approved).
     */
    private function mapAgency(Property $property): ?array
    {
        $contact = $property->displayContact();
        if (!$contact || $contact->type === 'company') {
            return null;
        }
        $owner = $property->owner;
        $agency = $owner && $owner->type === 'company' && $owner->id !== $contact->id ? $owner : $contact->company;

        return $agency && $agency->type === 'company' && $agency->is_active && $agency->isApproved()
            ? $this->mapProfile($agency)
            : null;
    }

    /** Agent / agency card data for the property page. */
    private function mapProfile(\App\Models\PortalUser $contact): array
    {
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
            // Their other listings (the Properties page filtered to them).
            'properties_url' => '/properties?' . ($contact->type === 'company' ? 'agency' : 'agent') . '=' . rawurlencode((string) $contact->slug),
        ];
    }
}
