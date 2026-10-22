<?php

namespace App\Services\PropertyFinder;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * One Property Finder listing (Enterprise API) read into flat values. Field names vary between API
 * versions, so each value is taken from the first path that has it.
 */
class PropertyFinderListing
{
    public function __construct(public readonly array $raw)
    {
    }

    public function id(): string
    {
        return (string) ($this->raw['id'] ?? '');
    }

    public function reference(): ?string
    {
        return $this->str('reference', 'referenceNumber', 'reference_number');
    }

    public function title(string $lang = 'en'): ?string
    {
        return $this->localized('title', $lang);
    }

    public function description(string $lang = 'en'): ?string
    {
        return $this->localized('description', $lang);
    }

    /** 'sale' | 'rent'. */
    public function purpose(): string
    {
        $type = Str::lower((string) ($this->pick('offeringType', 'offering_type', 'price.type') ?? ''));

        return match (true) {
            str_contains($type, 'rent'), in_array($type, ['yearly', 'monthly', 'weekly', 'daily'], true) => 'rent',
            default => 'sale',
        };
    }

    /** 'residential' | 'commercial'. */
    public function category(): string
    {
        return str_contains(Str::lower((string) ($this->pick('category', 'propertyCategory') ?? '')), 'commercial') ? 'commercial' : 'residential';
    }

    /** "Apartment", "Villa", "Office Space"… */
    public function type(): ?string
    {
        $type = $this->pick('type', 'propertyType', 'property_type');
        $type = is_array($type) ? ($type['name'] ?? $type['en'] ?? null) : $type;

        return $type ? Str::of((string) $type)->replace(['_', '-'], ' ')->title()->toString() : null;
    }

    /** Price in AED; a rent is its yearly amount. */
    public function price(): ?float
    {
        $amounts = (array) $this->pick('price.amounts');
        $value = $amounts['sale'] ?? $amounts['yearly'] ?? (isset($amounts['monthly']) ? $amounts['monthly'] * 12 : null)
            ?? $this->pick('price.value', 'price.amount', 'price');

        return is_numeric($value) ? (float) $value : null;
    }

    public function bedrooms(): ?int
    {
        $beds = $this->pick('bedrooms', 'beds');
        if (is_string($beds) && Str::lower($beds) === 'studio') {
            return 0;
        }

        return is_numeric($beds) ? (int) $beds : null;
    }

    public function bathrooms(): ?int
    {
        $baths = $this->pick('bathrooms', 'baths');

        return is_numeric($baths) ? (int) $baths : null;
    }

    /** Built-up area in sq ft (Property Finder sends sq ft; sqm is converted). */
    public function size(): ?float
    {
        $size = $this->pick('size', 'builtUpArea', 'area');
        $unit = Str::lower((string) ($this->pick('sizeUnit', 'size_unit', 'area.unit') ?? 'sqft'));
        if (is_array($size)) {
            $unit = Str::lower((string) ($size['unit'] ?? $unit));
            $size = $size['value'] ?? null;
        }
        if (!is_numeric($size)) {
            return null;
        }

        return round(str_contains($unit, 'm') && !str_contains($unit, 'ft') ? $size * 10.7639 : (float) $size, 2);
    }

    public function furnished(): ?string
    {
        return $this->str('furnishingType', 'furnishing', 'furnished');
    }

    /** The location id to look up (listings carry only its id). */
    public function locationId(): ?string
    {
        $location = $this->raw['location'] ?? null;
        $id = is_array($location) ? ($location['id'] ?? null) : $location;

        return $id !== null && $id !== '' ? (string) $id : $this->str('locationId', 'location_id');
    }

    /** Location names inline in the listing, most specific first, when it has them. */
    public function inlineLocationNames(): array
    {
        $location = $this->raw['location'] ?? null;
        if (!is_array($location)) {
            return [];
        }

        return self::locationNames($location);
    }

    /** Names of a location object and its parents (tree / path / community / city), most specific first. */
    public static function locationNames(array $location): array
    {
        $names = [];
        foreach (['name', 'tower', 'building', 'subCommunity', 'community', 'city'] as $key) {
            $value = $location[$key] ?? null;
            $value = is_array($value) ? ($value['name'] ?? $value['en'] ?? null) : $value;
            if (is_string($value) && trim($value) !== '') {
                $names[] = trim($value);
            }
        }
        // tree runs city → community → sub-community: most specific first.
        foreach (array_reverse((array) ($location['tree'] ?? $location['path'] ?? [])) as $node) {
            $value = is_array($node) ? ($node['name'] ?? $node['en'] ?? null) : $node;
            if (is_string($value) && trim($value) !== '') {
                $names[] = trim($value);
            }
        }

        return array_values(array_unique($names));
    }

    /** [lat, lng] from a location object, when it has them. */
    public static function coordinatesOf(?array $location): ?array
    {
        $point = $location['coordinates'] ?? $location['geo'] ?? $location['point'] ?? $location ?? [];
        $lat = $point['lat'] ?? $point['latitude'] ?? null;
        $lng = $point['lng'] ?? $point['lon'] ?? $point['longitude'] ?? null;

        return is_numeric($lat) && is_numeric($lng) ? [(float) $lat, (float) $lng] : null;
    }

    /** 'dubai', 'abu_dhabi', 'sharjah'… as Property Finder sends it (uaeEmirate). */
    public function emirate(): ?string
    {
        $value = $this->str('uaeEmirate', 'emirate');

        return $value ? Str::snake(Str::lower($value)) : null;
    }

    /** The listing's agent on Property Finder (assignedTo.name) — matched to an agency agent by name. */
    public function agentName(): ?string
    {
        return $this->str('assignedTo.name', 'agent.name');
    }

    /** The listing's agent's email, when Property Finder sends one. */
    public function agentEmail(): ?string
    {
        $email = $this->str('assignedTo.email', 'agent.email', 'createdBy.email', 'publicProfile.email');

        return $email && filter_var($email, FILTER_VALIDATE_EMAIL) ? Str::lower($email) : null;
    }

    /** 'off_plan' | 'ready' | null. */
    public function completionStatus(): ?string
    {
        $value = Str::lower((string) ($this->pick('completionStatus', 'projectStatus', 'completion_status') ?? ''));

        return match (true) {
            $value === '' => null,
            str_contains($value, 'off'), str_contains($value, 'plan'), str_contains($value, 'construction') => 'off_plan',
            default => 'ready',
        };
    }

    /** 'furnished' | 'semi_furnished' | 'unfurnished' | null. */
    public function furnishing(): ?string
    {
        $value = Str::lower((string) ($this->furnished() ?? ''));

        return match (true) {
            $value === '' => null,
            str_contains($value, 'semi'), str_contains($value, 'partly') => 'semi_furnished',
            str_contains($value, 'un'), $value === 'no', $value === 'false' => 'unfurnished',
            default => 'furnished',
        };
    }

    /** Image URLs, cover first (largest size available). */
    public function images(): array
    {
        $images = $this->pick('media.images', 'images', 'photos') ?? [];
        $urls = [];
        foreach ((array) $images as $image) {
            $url = is_string($image) ? $image : ($image['original']['url'] ?? $image['large']['url'] ?? $image['url'] ?? $image['link'] ?? null);
            if (is_string($url) && Str::startsWith($url, ['http://', 'https://'])) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    /** Amenity names ("Balcony", "Shared Pool"…). */
    public function amenities(): array
    {
        return collect((array) ($this->pick('amenities') ?? []))
            ->map(fn ($a) => is_array($a) ? ($a['name'] ?? $a['en'] ?? null) : $a)
            ->filter(fn ($a) => is_string($a) && $a !== '')
            ->map(fn ($a) => Str::of($a)->replace(['_', '-'], ' ')->title()->toString())
            ->unique()->values()->all();
    }

    public function permitNumber(): ?string
    {
        return $this->str('compliance.listingAdvertisementNumber', 'compliance.permitNumber', 'permitNumber', 'permit_number', 'rera');
    }

    /** Live on Property Finder (drafts / archived listings are not imported). */
    public function isLive(): bool
    {
        $live = $this->pick('portals.propertyfinder.isLive');
        if (is_bool($live)) {
            return $live;
        }
        $state = $this->pick('state.stage', 'state.type', 'state', 'status');
        $state = Str::lower(is_array($state) ? (string) ($state['stage'] ?? $state['type'] ?? '') : (string) $state);

        return $state === '' || in_array($state, ['live', 'published', 'active', 'approved'], true);
    }

    public function createdAt(): ?\Carbon\Carbon
    {
        $value = $this->str('createdAt', 'created_at', 'publishedAt');
        try {
            return $value ? \Carbon\Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function localized(string $key, string $lang): ?string
    {
        $value = $this->raw[$key] ?? null;
        if (is_array($value)) {
            $value = $value[$lang] ?? ($lang === 'en' ? (reset($value) ?: null) : null);
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function pick(string ...$paths): mixed
    {
        foreach ($paths as $path) {
            $value = Arr::get($this->raw, $path);
            if ($value !== null && $value !== '' && $value !== []) {
                return $value;
            }
        }

        return null;
    }

    private function str(string ...$paths): ?string
    {
        $value = $this->pick(...$paths);

        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
