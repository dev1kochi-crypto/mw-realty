<?php

namespace App\Http\Controllers\Api;

use App\Models\Property;
use App\Support\LocationFilter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/**
 * GET /api/location-suggestions?q=mar&lang=en[&scope=commercial]
 *
 * Location autocomplete for the Home search, the Properties listing and the Commercial page.
 * Suggestions come live from active properties (so a newly listed property's city, community and
 * address are suggested straight away): [{ type: city|community|address, label, sub, count?, slug? }].
 *
 * scope=commercial matches the /commercial page (Commercial-menu listings); the default scope
 * matches the Home search and /properties, which exclude those.
 *
 * @group Search & Filters
 */
class LocationSuggestionController extends Controller
{
    /**
     * Location autocomplete
     *
     * Cities, communities and addresses matching what the user typed (2+ characters; fewer
     * returns an empty list). Pass a picked suggestion back as `city=` / `community=` /
     * `location=` on the listing endpoints. Debounce on the client — limited to 120 calls/minute.
     *
     * @queryParam q string The typed text. Example: mar
     * @queryParam scope string all (default) or commercial. Example: all
     * @queryParam lang string Example: en
     */
    public function index(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string|max:100',
            'lang' => 'nullable|string|max:10',
            'scope' => 'nullable|in:all,commercial',
        ]);

        $term = trim((string) $request->input('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $lang = $request->input('lang', app()->getLocale());
        $scope = $request->input('scope', 'all');
        $cacheKey = 'location-suggest:' . md5("{$scope}|{$lang}|" . mb_strtolower($term));

        $suggestions = Cache::remember($cacheKey, 60, function () use ($term, $lang, $scope) {
            $query = Property::active()
                ->when($scope === 'commercial', fn ($q) => $q->commercial(), fn ($q) => $q->residential());

            return LocationFilter::suggest($query, $term, $lang);
        });

        return response()->json(['suggestions' => $suggestions]);
    }
}
