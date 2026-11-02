<?php

namespace App\Services\Properties;

use App\Models\Filter;
use App\Models\FilterValue;
use App\Support\PermitRules;
use Illuminate\Support\Str;

/**
 * Turns the free text an outside feed sends (Property Finder's API, an agency's spreadsheet) into
 * our Master › Property Options values: "Apartment / Flat" → apartment, "Jumeirah Village Circle"
 * → the location option, "Pool, Gym" → the amenity options. Shared by PropertyFinderImporter and
 * PropertiesImport so both feeds map listings the same way. Option lists are loaded once per run.
 */
class ListingOptionMatcher
{
    /** Option lists by filter key. */
    private array $cache = [];

    /** The option value whose value or label matches one of $names (most specific first), or null. */
    public function match(string $filterKey, array $names): ?string
    {
        $options = $this->cache[$filterKey] ??= FilterValue::whereHas('filter', fn ($q) => $q->where('key', $filterKey))->get(['value', 'translations']);

        foreach ($names as $name) {
            $slug = Str::slug((string) $name);
            if ($slug === '') {
                continue;
            }
            foreach ($options as $option) {
                $labels = collect($option->translations)->pluck('label')->filter()->map(fn ($l) => Str::slug($l));
                if (Str::slug($option->value) === $slug || $labels->contains($slug)) {
                    return $option->value;
                }
            }
        }

        return null;
    }

    /** A property type in any wording ("Flat", "Penthouse", "Retail Shop") → the property_type option. */
    public function propertyType(?string $type): ?string
    {
        if (!$type) {
            return null;
        }
        $t = Str::lower($type);
        $guess = match (true) {
            str_contains($t, 'penthouse') => 'penthouse',
            str_contains($t, 'townhouse') => 'townhouse',
            str_contains($t, 'villa') => 'villa',
            str_contains($t, 'apartment'), str_contains($t, 'flat'), str_contains($t, 'duplex'), str_contains($t, 'loft'), str_contains($t, 'studio') => 'apartment',
            str_contains($t, 'office') => 'office',
            str_contains($t, 'retail'), str_contains($t, 'shop') => 'retail-shop',
            str_contains($t, 'showroom') => 'showroom',
            str_contains($t, 'warehouse') => 'warehouse',
            str_contains($t, 'land'), str_contains($t, 'plot') => 'plot',
            str_contains($t, 'building') => 'whole-building',
            default => Str::slug($type),
        };

        return $this->match('property_type', [$guess, $type]);
    }

    /** Adds a community (e.g. "Arjan") to the Location options and returns its value. */
    public function addLocation(?string $community): ?string
    {
        $filter = $community ? Filter::where('key', 'location')->first() : null;
        if (!$filter || ($value = Str::slug($community)) === '') {
            return null;
        }

        FilterValue::firstOrCreate(['filter_id' => $filter->id, 'value' => $value], [
            'translations' => ['en' => ['label' => $community]],
            'order_index' => (int) FilterValue::where('filter_id', $filter->id)->max('order_index') + 1,
            'status' => true,
        ]);
        unset($this->cache['location']); // match() sees it from now on

        return $value;
    }

    /** Amenity names → the matching Amenity options, stored like the property form does. */
    public function amenities(array $names): array
    {
        if (!$names) {
            return [];
        }
        $slugs = array_map(fn ($n) => Str::slug($n), $names);

        return FilterValue::whereHas('filter', fn ($q) => $q->where('key', 'amenity'))->orderBy('order_index')->orderBy('id')->get()
            ->filter(fn ($o) => in_array(Str::slug($o->value), $slugs, true)
                || collect($o->translations)->pluck('label')->filter()->contains(fn ($l) => in_array(Str::slug($l), $slugs, true)))
            ->map(fn ($o) => [
                'key' => $o->value,
                'icon' => $o->icon,
                'label' => collect($o->translations)->map(fn ($t) => $t['label'] ?? '')->filter()->all(),
            ])->values()->all();
    }

    /** The emirate (PermitRules value) a set of place names is in, or null. */
    public function emirateFromNames(array $names): ?string
    {
        $all = Str::lower(implode(' | ', array_filter($names)));

        return match (true) {
            $all === '' => null,
            str_contains($all, 'dubai') => PermitRules::DUBAI,
            str_contains($all, 'abu dhabi'), str_contains($all, 'abu_dhabi'), str_contains($all, 'al ain') => PermitRules::ABU_DHABI,
            (bool) preg_match('/sharjah|ajman|ras al khaimah|umm al quwain|fujairah|northern/', $all) => PermitRules::NORTHERN,
            default => null,
        };
    }
}
