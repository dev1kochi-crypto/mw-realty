<?php

namespace App\Services;

use App\Models\Filter;
use App\Models\Property;
use App\Models\SearchFilter;
use App\Support\SearchFilterFields;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the website search filters of one listing page (GET /api/search-filters/{page}) from
 * Admin › Filters › Search Filters. Options always come from the database: the field's master
 * option list (or the listings' own values) for "auto", the admin's own list for "custom".
 */
class SearchFilterService
{
    private const CACHE_TTL = 180; // seconds — same as the listings

    /** Admin saves bump this so every cached filter set is rebuilt straight away. */
    public static function version(): int
    {
        return (int) Cache::get('search-filters:version', 1);
    }

    public static function flush(): void
    {
        Cache::forever('search-filters:version', self::version() + 1);
    }

    /** The active filters of a page, in admin order (cached; used by the API and the listing query). */
    public function definitions(string $page): Collection
    {
        return Cache::remember('search-filters:defs:' . $page . ':v' . self::version(), self::CACHE_TTL, fn () => SearchFilter::active()
            ->forPage($page)
            ->with('activeOptions')
            ->get()
            ->filter(fn (SearchFilter $f) => SearchFilterFields::get($f->field) && SearchFilterFields::allows($f->field, $f->type))
            ->values());
    }

    /**
     * Key-value payload: { page, filters: { <key>: {label, type, param(s), options: {value: label}, …} }, order: [keys] }.
     * JSON objects don't keep numeric keys in order ("1", "2", "studio"), so `order` and each
     * filter's `options_order` give the admin's order.
     */
    public function payload(string $page, string $lang): array
    {
        return Cache::remember("search-filters:payload:{$page}:{$lang}:v" . self::version(), self::CACHE_TTL, function () use ($page, $lang) {
            $filters = [];
            foreach ($this->definitions($page) as $filter) {
                $filters[$filter->field] = $this->filterPayload($filter, $page, $lang);
            }

            return ['page' => $page, 'filters' => (object) $filters, 'order' => array_keys($filters)];
        });
    }

    private function filterPayload(SearchFilter $filter, string $page, string $lang): array
    {
        $field = SearchFilterFields::get($filter->field);
        $payload = [
            'label' => $filter->label($lang),
            'type' => $filter->type,
            'placeholder' => $filter->getTranslation('placeholder', $lang),
        ];

        if ($filter->hasOptions()) {
            $options = $this->options($filter, $page, $lang);
            $payload['param'] = $filter->field;
            // checkbox: several values, sent comma-separated (?amenity=gym,security) or as an array.
            $payload['multiple'] = $filter->type === 'checkbox';
            $payload['options'] = (object) $options->pluck('label', 'value')->all();
            $payload['options_order'] = $options->pluck('value')->all();
            $counts = $options->filter(fn ($o) => isset($o['count']))->pluck('count', 'value');
            if ($counts->isNotEmpty()) $payload['counts'] = (object) $counts->all();
        } elseif ($filter->type === 'text') {
            $payload['param'] = $filter->field;
        } else {
            $max = $this->maxValue($field, $page);
            $round = $field['round'] ?? 1;
            $payload['min'] = (float) $filter->setting('min', 0);
            $payload['max'] = (float) ($filter->setting('max') ?: max($round, ceil($max / $round) * $round));
            $payload['step'] = (float) ($filter->setting('step') ?: ($field['step'] ?? 1));
            if ($filter->type === 'range') {
                $payload['params'] = ['min' => 'min_' . $filter->field, 'max' => 'max_' . $filter->field];
                // Price: the quick-select buttons — [{min, max (null = and up), label}].
                if ($filter->field === 'price') {
                    $payload['presets'] = $this->options($filter, $page, $lang)->map(function ($o) {
                        [$min, $max] = array_pad(explode('-', $o['value'], 2), 2, '');
                        return ['min' => (float) $min, 'max' => $max === '' ? null : (float) $max, 'label' => $o['label']];
                    })->values()->all();
                }
            } else {
                // number: "at least" (min_), "at most" (max_) or "exactly" (the key itself).
                $compare = $filter->setting('compare', 'min');
                $payload['compare'] = $compare;
                $payload['param'] = match ($compare) {
                    'max' => 'max_' . $filter->field,
                    'exact' => $filter->field,
                    default => 'min_' . $filter->field,
                };
            }
        }

        return $payload;
    }

    /** [{value, label, count?}] in admin order. */
    public function options(SearchFilter $filter, string $page, string $lang): Collection
    {
        $field = SearchFilterFields::get($filter->field);
        // Built-in values (sort, features, amenity shortcuts, price quick-select): their default labels.
        $builtIn = $this->builtInOptions($filter, $page, $lang);

        if ($filter->options_source === 'custom') {
            return $filter->activeOptions
                ->filter(fn ($o) => $builtIn === null || $builtIn->has((string) $o->value) || $filter->type === 'range')
                ->map(fn ($o) => [
                    'value' => (string) $o->value,
                    // A custom row left without a label keeps the built-in one (in every language).
                    'label' => ($o->translations[$lang]['label'] ?? null) ?: ($builtIn?->get((string) $o->value) ?: $o->label($lang)),
                ])->values();
        }

        if ($builtIn !== null) {
            return $builtIn->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label])->values();
        }
        $master = $field['master'] ? $this->masterValues($field['master'], $lang) : collect();
        $humanize = fn ($v) => ucwords(str_replace(['_', '-'], ' ', (string) $v));

        // Lists matched against JSON rows, fixed buckets (bedrooms "5+") and furnishing: the master list as it is.
        if ($field['storage'] === 'details_list' || in_array($filter->field, ['bedrooms', 'bathrooms', 'furnishing'], true)) {
            return $master->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label])->values();
        }

        // Plain property fields: only values live listings actually have (an option never leads to
        // an empty page), labelled and ordered by the master list, then any other value found.
        $counts = $this->valueCounts($field, $page);
        $disabled = $field['master'] ? $this->disabledMasterValues($field['master']) : [];
        $counts = $counts->except($disabled);
        $ordered = $master->keys()->map(fn ($v) => (string) $v)->filter(fn ($v) => $counts->has($v))
            ->concat($counts->keys()->map(fn ($v) => (string) $v)->diff($master->keys()->map(fn ($v) => (string) $v))->sort());

        return $ordered->map(fn ($value) => [
            'value' => (string) $value,
            'label' => $master->get($value) ?: $humanize($value),
            'count' => (int) $counts[$value],
        ])->values();
    }

    /** value => label for fields with a fixed value set / price presets; null for database fields. */
    private function builtInOptions(SearchFilter $filter, string $page, string $lang): ?Collection
    {
        $field = SearchFilterFields::get($filter->field);
        $section = SearchFilterFields::textSection($page);

        if ($filter->type === 'range' && $filter->field === 'price') {
            return collect(SearchFilterFields::PRICE_PRESETS)->map(fn ($def) => $this->text("{$section}.{$def[1]}", $lang, $def[0]));
        }
        if (empty($field['fixed'])) return null;

        return collect($field['fixed'])->map(function ($def) use ($section, $lang) {
            // The key of this page's section first (commercial.* on /commercial), then any other.
            $keys = collect($def[1])->sortBy(fn ($k) => str_starts_with($k, $section . '.') ? 0 : 1);
            foreach ($keys as $key) {
                if ($text = $this->text($key, $lang, null)) return $text;
            }
            return $def[0];
        });
    }

    private array $texts = [];

    /** A static-translation text (Admin › Static Translations) in $lang, else English, else $default. */
    private function text(string $key, string $lang, ?string $default): ?string
    {
        foreach (array_unique([$lang, 'en']) as $code) {
            $this->texts[$code] ??= (array) app(\CMS\SiteManager\Services\StaticTranslationService::class)->read($code);
            $value = data_get($this->texts[$code], $key);
            if (is_string($value) && $value !== '') return $value;
        }
        return $default;
    }

    /** value => label of an active master option list, in its order. */
    private function masterValues(string $key, string $lang): Collection
    {
        $filter = Filter::where('key', $key)->with('activeValues')->first();
        return $filter
            ? $filter->activeValues->mapWithKeys(fn ($v) => [(string) $v->value => $v->getTranslation('label', $lang) ?: $v->value])
            : collect();
    }

    private function disabledMasterValues(string $key): array
    {
        $filter = Filter::where('key', $key)->first();
        return $filter ? $filter->values()->where('status', false)->pluck('value')->map(fn ($v) => (string) $v)->all() : [];
    }

    /** value => number of live listings on this page with it. */
    private function valueCounts(array $field, string $page): Collection
    {
        $query = $this->listings($page);
        if ($field['storage'] === 'details') {
            $column = 'property_details.' . $field['column'];
            $query->join('property_details', 'property_details.property_id', '=', 'properties.id');
        } else {
            $column = 'properties.' . $field['column'];
        }

        return $query->whereNotNull($column)->where($column, '!=', '')
            ->selectRaw("{$column} as value, count(*) as total")
            ->groupBy($column)
            ->pluck('total', 'value')
            ->mapWithKeys(fn ($total, $value) => [(string) $value => $total]);
    }

    private function maxValue(array $field, string $page): float
    {
        $query = $this->listings($page);
        if ($field['storage'] === 'details') {
            return (float) ($query->join('property_details', 'property_details.property_id', '=', 'properties.id')->max('property_details.' . $field['column']) ?? 0);
        }
        return (float) ($query->max('properties.' . $field['column']) ?? 0);
    }

    /** The live listings a page shows — option counts and slider bounds follow them. */
    public function listings(string $page)
    {
        return match ($page) {
            'commercial' => Property::active()->commercial(),
            'premium' => Property::active()->where('properties.featured', true),
            'offplan' => Property::active()->where('properties.completion_status', 'off_plan'),
            default => Property::active()->residential(),
        };
    }

    /** The listing page whose filter set a listing request uses (marketing / agent lists use Properties). */
    public static function pageFor(?string $segment): string
    {
        return match ($segment) {
            'commercial' => 'commercial',
            'premium' => 'premium',
            'offplan' => 'offplan',
            default => 'properties',
        };
    }

    /** Values of a database-backed field, for the admin form's "from the database" preview. */
    public function previewOptions(string $fieldKey, string $page, string $lang, string $type = 'select'): Collection
    {
        $filter = new SearchFilter(['page' => $page, 'field' => $fieldKey, 'type' => $type, 'options_source' => 'auto']);
        return $this->options($filter, $page, $lang);
    }
}
