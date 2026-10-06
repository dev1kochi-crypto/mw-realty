<?php

namespace App\Services;

use App\Models\Property;

/**
 * Listing quality score out of 100 — how complete and trustworthy a listing looks to buyers, with
 * a breakdown and a tip for every check that isn't full marks (portal listing cards + Listing
 * Performance panel). Pure calculation from the listing's own data; nothing is stored.
 *
 * Expects details / floorPlans loaded (or it lazy-loads them).
 */
class ListingQualityService
{
    /**
     * @return array{score: int, max: int, tone: string, groups: array<int, array<int, array{label: string, points: int, max: int, tip: ?string}>>}
     */
    public function score(Property $property): array
    {
        $details = $property->details;
        $groups = [
            [
                $this->title($property),
                $this->description($property),
            ],
            [
                $this->images($property),
                $this->media($property, $details),
            ],
            [
                $this->completion($property, $details),
                $this->location($property),
                $this->verification($property),
            ],
        ];

        $score = (int) collect($groups)->flatten(1)->sum('points');

        return [
            'score' => $score,
            'max' => 100,
            'tone' => $score >= 80 ? 'good' : ($score >= 50 ? 'fair' : 'poor'),
            'groups' => $groups,
        ];
    }

    private function check(string $label, int $points, int $max, ?string $tip): array
    {
        return ['label' => $label, 'points' => max(0, min($points, $max)), 'max' => $max, 'tip' => $points >= $max ? null : $tip];
    }

    private function title(Property $property): array
    {
        $length = mb_strlen(trim((string) $property->getTranslation('title')));

        return $this->check('Title', match (true) {
            $length >= 30 && $length <= 90 => 10,
            $length >= 15 => 6,
            $length > 0 => 3,
            default => 0,
        }, 10, 'Use a clear title of 30–90 characters (type, size, community, a key selling point).');
    }

    private function description(Property $property): array
    {
        $words = str_word_count(strip_tags((string) $property->getTranslation('description')));

        return $this->check('Description', match (true) {
            $words >= 150 => 15,
            $words >= 80 => 10,
            $words >= 30 => 5,
            default => 0,
        }, 15, "Write at least 150 words describing the property ({$words} now).");
    }

    private function images(Property $property): array
    {
        $count = count($property->galleryNumbers());

        return $this->check('Images', match (true) {
            $count >= 10 => 15,
            $count >= 6 => 10,
            $count >= 3 => 6,
            $count >= 1 => 3,
            default => 0,
        }, 15, "Add at least 10 good photos ({$count} now).");
    }

    private function media($property, $details): array
    {
        $floorPlan = $property->floorPlans->isNotEmpty() || filled($details?->floor_plan_image) || filled($details?->floor_plan_file);
        $video = filled($details?->video_tour_url) || filled($details?->virtual_tour_url);

        return $this->check('Floor plan & video', ($floorPlan ? 3 : 0) + ($video ? 2 : 0), 5,
            !$floorPlan ? 'Add a floor plan.' : 'Add a video or virtual tour.');
    }

    private function completion(Property $property, $details): array
    {
        $residential = $property->category !== 'commercial';
        $fields = array_filter([
            'property type' => filled($property->property_type),
            'price' => (float) $property->price > 0,
            'area' => (float) $property->sqft > 0,
            'bedrooms' => $residential ? filled($property->bedrooms) : null,
            'bathrooms' => $residential ? filled($property->bathrooms) : null,
            'completion status' => filled($property->completion_status),
            'furnishing' => filled($details?->furnished),
            'amenities' => count((array) ($details?->amenities ?? [])) >= 3,
            'key features' => filled($property->getTranslation('key_features')),
        ], fn ($v) => $v !== null);
        $missing = array_keys(array_filter($fields, fn ($filled) => !$filled));

        return $this->check('Listing details', (int) round(20 * (count($fields) - count($missing)) / max(1, count($fields))), 20,
            $missing ? 'Fill in: ' . implode(', ', $missing) . '.' : null);
    }

    private function location(Property $property): array
    {
        $points = (filled($property->getTranslation('city')) || filled($property->location) ? 3 : 0)
            + (filled($property->getTranslation('community')) ? 3 : 0)
            + (filled($property->getTranslation('address')) ? 3 : 0)
            + ($property->latitude && $property->longitude ? 6 : 0);

        return $this->check('Location', $points, 15,
            !($property->latitude && $property->longitude) ? 'Pin the exact location on the map.' : 'Add the city, community and address.');
    }

    private function verification(Property $property): array
    {
        $points = match (true) {
            $property->compliance_status === Property::COMPLIANCE_APPROVED && filled($property->permit_number) => 20,
            filled($property->permit_number) => 10,
            default => 0,
        };

        return $this->check('Listing verification', $points, 20,
            filled($property->permit_number) ? 'Validate the permit so the listing is verified.' : 'Add and validate the advertising permit.');
    }
}
