<?php

namespace App\Http\Controllers;

use App\Models\Filter;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/**
 * @group Search & Filters
 *
 * Public read-only endpoint the frontend (home banner + /properties listing)
 * uses to render the search filter bar. Options come straight from the
 * admin-managed filters/filter_values tables, so a new filter or option
 * shows up here without a code change. Option counts and slider bounds use
 * the listings of the page asking: residential by default, or `?scope=commercial` for /commercial.
 */
class PropertyFilterController extends Controller
{
    /**
     * Search filter bar
     *
     * The filters to render (selects with their options and live listing counts, number sliders
     * with min/max/step), in admin order. Pass the chosen `value`s back to the listing endpoints
     * under each filter's `key`.
     *
     * @queryParam page string Which screen's filter set: `home` (compact) or `listing` (full). Example: listing
     * @queryParam scope string Whose listings the counts/bounds come from: residential, commercial, premium, marketing. Example: residential
     * @queryParam lang string Example: en
     */
    public function index(Request $request)
    {
        $request->validate(['page' => 'sometimes|in:home,listing', 'lang' => 'sometimes|string|max:10', 'scope' => 'sometimes|in:residential,commercial,premium,marketing']);
        $page = $request->input('page', 'home'); // "home" or "listing"
        $lang = $request->input('lang', app()->getLocale());
        // Which listings option counts and slider bounds come from: /properties (default),
        // /commercial, or /premium-properties (featured listings of both).
        $listings = fn () => match ($request->input('scope')) {
            'commercial' => Property::active()->commercial(),
            'premium' => Property::active()->where('featured', true),
            'marketing' => Property::active()->marketing(),
            default => Property::active()->residential(),
        };

        // A grouped count per select filter plus a MAX() per slider on every call — cached briefly
        // (same TTL as the listings themselves), keyed by everything that changes the answer.
        $scope = $request->input('scope', 'residential');
        $filters = Cache::remember("property-filters:{$page}:{$scope}:{$lang}", self::CACHE_TTL, fn () => Filter::active()->whereIn('key', array_merge(Filter::SELECT_KEYS, Filter::NUMBER_KEYS))
            ->shownOn($page)
            ->orderBy('order_index')
            ->with(['activeValues'])
            ->get()
            ->map(function (Filter $filter) use ($lang, $listings) {
                $payload = [
                    'key' => $filter->key,
                    'label' => $filter->getTranslation('label', $lang),
                    'type' => $filter->type,
                ];

                if ($filter->type === 'select') {
                    $payload['options'] = $this->selectOptions($filter, $lang, $listings);
                } elseif (in_array($filter->key, Filter::NUMBER_KEYS, true)) {
                    // Slider bounds follow the live listings; max is rounded up to a whole step so
                    // the slider ends on a clean number (e.g. 18.9M -> 19M).
                    $max = (float) ($listings()->max($filter->key) ?? 0);
                    $step = $filter->key === 'price' ? 50000 : 50;
                    $round = $filter->key === 'price' ? 1000000 : 500;
                    $payload['min'] = 0;
                    $payload['max'] = max($round, ceil($max / $round) * $round);
                    $payload['step'] = $step;
                }

                return $payload;
            })
            ->values()
            ->all());

        return response()->json(['filters' => $filters]);
    }

    private const CACHE_TTL = 180; // seconds — same as PropertiesPageService

    /** Filters whose options are a fixed admin list (every value is meaningful even with no listings yet). */
    private const ADMIN_LIST_KEYS = ['bedrooms', 'bathrooms'];

    /**
     * Options for a select filter. For property columns (listing_type, property_type, location, …)
     * the VALUES come from the active listings themselves, so an option never leads to an empty
     * result; the admin's filter values supply the label and order, and a value the admin has
     * switched off stays hidden. Bedrooms/bathrooms keep the admin's fixed list.
     */
    private function selectOptions(Filter $filter, string $lang, \Closure $listings): array
    {
        $adminValues = $filter->activeValues->keyBy('value');
        $label = fn ($value) => $adminValues->get($value)?->getTranslation('label', $lang)
            ?: ucwords(str_replace(['_', '-'], ' ', (string) $value));

        if (in_array($filter->key, self::ADMIN_LIST_KEYS, true) || !in_array($filter->key, Filter::SELECT_KEYS, true)) {
            return $adminValues->map(fn ($v) => ['value' => $v->value, 'label' => $label($v->value)])->values()->all();
        }

        // Admin-disabled values stay hidden even if some listing still uses them.
        $disabled = $filter->values()->where('status', false)->pluck('value')->all();
        $counts = $listings()
            ->whereNotNull($filter->key)->where($filter->key, '!=', '')
            ->selectRaw("{$filter->key} as value, count(*) as total")
            ->groupBy($filter->key)
            ->pluck('total', 'value')
            ->except($disabled);

        // Admin order first (values the admin has defined), then any other value found on listings.
        $ordered = $adminValues->keys()->filter(fn ($v) => $counts->has($v))
            ->concat($counts->keys()->diff($adminValues->keys())->sort());

        return $ordered->map(fn ($value) => [
            'value' => (string) $value,
            'label' => $label($value),
            'count' => (int) $counts[$value],
        ])->values()->all();
    }
}
