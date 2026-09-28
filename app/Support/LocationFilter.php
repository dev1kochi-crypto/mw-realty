<?php

namespace App\Support;

use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Location search shared by the Properties listing, the Commercial listing and the location
 * autocomplete. Properties keep address/community/city per language in `translations`, plus a
 * `location` filter slug; every supported language is searched so e.g. an Arabic query works too.
 */
class LocationFilter
{
    private const LANGS = ['en', 'ar'];

    /**
     * @param  string|null  $city       exact city (chosen from a suggestion)
     * @param  string|null  $community  exact community (chosen from a suggestion)
     * @param  string|null  $text       free text typed without picking a suggestion
     */
    public static function apply(Builder $query, ?string $city = null, ?string $community = null, ?string $text = null): Builder
    {
        if ($city) {
            $query->where(fn ($q) => self::anyLang($q, 'city', '=', $city));
        }
        if ($community) {
            $query->where(fn ($q) => self::anyLang($q, 'community', '=', $community));
        }
        if ($text) {
            $slug = str_replace(' ', '-', mb_strtolower($text));
            $query->where(function ($q) use ($text, $slug) {
                foreach (['address', 'community', 'city'] as $field) {
                    self::anyLang($q, $field, 'like', "%{$text}%");
                }
                $q->orWhere('location', 'like', "%{$slug}%");
            });
        }

        return $query;
    }

    /**
     * Autocomplete: matching cities, communities and property addresses for $term.
     * Cities/communities are de-duplicated with a listing count; addresses point at their property.
     *
     * @return array<int, array{type: string, label: string, sub: ?string, count?: int, slug?: string}>
     */
    public static function suggest(Builder $scope, string $term, string $lang, int $limit = 8): array
    {
        $needle = mb_strtolower($term);
        $matches = fn (?string $v) => $v !== null && $v !== '' && str_contains(mb_strtolower($v), $needle);

        $candidates = (clone $scope)
            ->where(function ($q) use ($term) {
                foreach (['address', 'community', 'city'] as $field) {
                    self::anyLang($q, $field, 'like', "%{$term}%");
                }
            })
            ->limit(200)
            ->get(['id', 'slug', 'translations']);

        // Show the visitor's language, falling back to English when a field isn't translated.
        $field = fn (Property $p, string $key) => $p->translations[$lang][$key] ?? $p->translations['en'][$key] ?? null;
        // A property matches a field if the term is found in any language's version of it.
        $hit = fn (Property $p, string $key) => collect(self::LANGS)->contains(fn ($l) => $matches($p->translations[$l][$key] ?? null));

        $group = function (string $key, ?callable $sub = null) use ($candidates, $field, $hit): Collection {
            return $candidates->filter(fn ($p) => $hit($p, $key) && $field($p, $key))
                ->groupBy(fn ($p) => $field($p, $key))
                ->map(fn ($items, $label) => [
                    'type' => $key,
                    'label' => $label,
                    'sub' => $sub ? $sub($items->first()) : null,
                    'count' => $items->count(),
                ])
                ->sortByDesc('count')
                ->values();
        };

        $cities = $group('city');
        $communities = $group('community', fn ($p) => $field($p, 'city'));
        $addresses = $candidates->filter(fn ($p) => $hit($p, 'address') && $field($p, 'address'))
            ->take(5)
            ->map(fn ($p) => [
                'type' => 'address',
                'label' => $field($p, 'address'),
                'sub' => $p->getTranslation('title', $lang),
                'slug' => $p->slug,
            ])
            ->values();

        return $cities->take(3)->concat($communities->take(4))->concat($addresses)->take($limit)->values()->all();
    }

    private static function anyLang($q, string $field, string $operator, string $value): void
    {
        foreach (self::LANGS as $lang) {
            $q->orWhere("translations->{$lang}->{$field}", $operator, $value);
        }
    }
}
