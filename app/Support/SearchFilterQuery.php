<?php

namespace App\Support;

use App\Services\SearchFilterService;

/**
 * Applies the website search filters (Admin › Filters › Search Filters) to a listing query.
 *
 * Request params are key-value, keyed by the filter's field:
 *   ?{field}=value            one value (dropdown / radio / exact number)
 *   ?{field}=a,b  or {field}[]  several values, any of them (checkboxes)
 *   ?{field}=text             a text box: contains the text (view, developer, keyword, reference_no)
 *   ?min_{field}=&max_{field}= a range, or a number "at least" / "at most"
 * Fields PropertiesPageService::applyFilters already reads (SearchFilterFields "legacy") are left to it.
 */
class SearchFilterQuery
{
    private const MAX_VALUES = 30;

    /** Picks this page's search-filter params out of the request: [field => ['in' => [...]] | ['like' => s] | ['min' => n, 'max' => n]]. */
    public static function fromRequest(array $input, string $page): array
    {
        $textFields = app(SearchFilterService::class)->definitions($page)
            ->where('type', 'text')->pluck('field')->all();

        $criteria = [];
        foreach (SearchFilterFields::all() as $key => $field) {
            $legacy = $field['legacy'] ?? null;

            if ($legacy === null && array_key_exists($key, $input)) {
                $isText = $field['types'] === ['text'] || in_array($key, $textFields, true);
                if ($isText) {
                    $text = is_scalar($input[$key]) ? trim((string) $input[$key]) : '';
                    if ($text !== '') $criteria[$key] = ['like' => mb_substr($text, 0, 100)];
                } elseif ($values = self::values($input[$key])) {
                    $criteria[$key] = ['in' => $values];
                }
            }

            if (($field['numeric'] ?? false) && $legacy !== 'range') {
                $min = self::number($input['min_' . $key] ?? null);
                $max = self::number($input['max_' . $key] ?? null);
                if ($min !== null || $max !== null) {
                    $criteria[$key] = array_merge($criteria[$key] ?? [], ['min' => $min, 'max' => $max]);
                }
            }
        }

        return self::normalise($criteria);
    }

    /** Stable shape / order, so the same search shares one cache entry. */
    public static function normalise(array $criteria): array
    {
        $clean = [];
        foreach ($criteria as $key => $rule) {
            if (!SearchFilterFields::get($key) || !is_array($rule)) continue;
            if (isset($rule['in'])) {
                $in = array_values(array_unique(array_map('strval', array_filter((array) $rule['in'], 'is_scalar'))));
                sort($in);
                if ($in) $clean[$key]['in'] = array_slice($in, 0, self::MAX_VALUES);
            }
            if (isset($rule['like']) && is_string($rule['like']) && trim($rule['like']) !== '') {
                $clean[$key]['like'] = mb_substr(trim($rule['like']), 0, 100);
            }
            foreach (['min', 'max'] as $bound) {
                if (isset($rule[$bound]) && is_numeric($rule[$bound])) $clean[$key][$bound] = (float) $rule[$bound];
            }
        }
        ksort($clean);

        return $clean;
    }

    public static function apply($query, array $criteria): void
    {
        foreach ($criteria as $key => $rule) {
            $field = SearchFilterFields::get($key);
            if (!$field) continue;

            match ($field['storage']) {
                'keyword' => self::keyword($query, $rule['like'] ?? implode(' ', $rule['in'] ?? [])),
                'details' => $query->whereHas('details', fn ($q) => self::where($q, 'property_details.' . $field['column'], $rule)),
                'details_list' => $query->whereHas('details', fn ($q) => $q->where(function ($q) use ($field, $rule) {
                    foreach ($rule['in'] ?? [] as $value) $q->orWhereJsonContains('property_details.' . $field['column'], ['key' => $value]);
                })),
                default => self::where($query, 'properties.' . $field['column'], $rule),
            };
        }
    }

    private static function where($query, string $column, array $rule): void
    {
        if (!empty($rule['in'])) $query->whereIn($column, $rule['in']);
        if (isset($rule['like'])) $query->where($column, 'like', '%' . addcslashes($rule['like'], '%_\\') . '%');
        if (isset($rule['min'])) $query->where($column, '>=', $rule['min']);
        if (isset($rule['max'])) $query->where($column, '<=', $rule['max']);
    }

    /** Title (any language) or reference number contains the text. Not case-sensitive. */
    private static function keyword($query, string $text): void
    {
        $text = trim($text);
        if ($text === '') return;
        $like = '%' . addcslashes(mb_strtolower($text), '%_\\') . '%';
        $query->where(function ($q) use ($like) {
            $q->whereRaw('LOWER(properties.reference_no) LIKE ?', [$like]);
            foreach (['en', 'ar'] as $code) {
                $q->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(properties.translations, '$.\"{$code}\".\"title\"'))) LIKE ?", [$like]);
            }
        });
    }

    /** "a,b" / ["a","b"] / "a" -> ['a','b'] (trimmed, non-empty, short). */
    private static function values($raw): array
    {
        $list = is_array($raw) ? $raw : explode(',', (string) $raw);
        return array_slice(array_values(array_filter(
            array_map(fn ($v) => is_scalar($v) ? mb_substr(trim((string) $v), 0, 100) : '', $list),
            fn ($v) => $v !== ''
        )), 0, self::MAX_VALUES);
    }

    private static function number($v): ?float
    {
        return is_numeric($v) && $v >= 0 ? (float) $v : null;
    }
}
