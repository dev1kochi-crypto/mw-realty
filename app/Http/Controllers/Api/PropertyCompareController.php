<?php

namespace App\Http\Controllers\Api;

use App\Models\Currency;
use App\Services\CompareUnlock;
use App\Services\PropertyCompareService;
use App\Services\PropertyCompareSummary;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public endpoints for the /compare page.
 *
 * @group Properties
 */
class PropertyCompareController extends Controller
{
    public function __construct(private readonly PropertyCompareService $compare)
    {
    }

    /**
     * Compare properties
     *
     * Two to five listings side by side in one shape, whichever emirate they are in: price (rent per
     * year), size and price per sq.ft, specs, amenities, nearest place of each type, buying costs
     * under that emirate's fees (move-in costs for rentals), market estimates (rent, gross / net
     * yield, price vs area, growth), ownership rules and Golden Visa eligibility. `notes` carries the
     * cross-border notes (FX margin, UAE tax, notes per buyer currency). Amounts in AED.
     *
     * The money & investment figures (`costs`, `total_outlay`, `market`) come back null with
     * `locked: true` until the visitor has left their contact details — send the token from
     * `POST /api/contact` (source=compare) as the `X-Compare-Unlock` header.
     *
     * @queryParam slugs string required Comma-separated listing slugs, in display order. Example: virella-2,marina-gate-1
     * @queryParam lang string Example: en
     */
    public function index(Request $request)
    {
        $data = $this->compare->build(PropertyCompareService::slugs($request->input('slugs')), $request->input('lang', app()->getLocale()));
        $unlocked = CompareUnlock::valid($request->header(CompareUnlock::HEADER));

        return response()->json(($unlocked ? $data : PropertyCompareService::locked($data)) + [
            'locked' => !$unlocked,
            'limits' => ['min' => (int) config('property_compare.min_items'), 'max' => (int) config('property_compare.max_items')],
        ])->header('Vary', CompareUnlock::HEADER);
    }

    /**
     * Comparison summary
     *
     * A written summary of the same listings — the main trade-off, 4–6 key differences and who each
     * listing suits (`best_for`, keyed by position 1..n) — with amounts in `currency`. `source` is
     * `ai`, or `rules` when the AI isn't available. Without the `X-Compare-Unlock` header it is
     * written without the locked money & investment figures.
     *
     * @queryParam slugs string required Example: virella-2,marina-gate-1
     * @queryParam currency string A code from GET /api/currencies. Example: USD
     * @queryParam lang string Example: en
     */
    public function summary(Request $request, PropertyCompareSummary $summary)
    {
        $lang = $request->input('lang', app()->getLocale());
        $data = $this->compare->build(PropertyCompareService::slugs($request->input('slugs')), $lang);

        if (!CompareUnlock::valid($request->header(CompareUnlock::HEADER))) {
            $data = PropertyCompareService::locked($data);
        }

        if (count($data['properties']) < (int) config('property_compare.min_items')) {
            return response()->json(['message' => 'Pick at least ' . config('property_compare.min_items') . ' properties to compare.'], 422);
        }

        $currency = Currency::active()->where('code', strtoupper((string) $request->input('currency')))->first()
            ?? new Currency(['code' => Currency::BASE, 'rate' => 1]);

        return response()->json($summary->summarize($data, $currency, $lang))->header('Vary', CompareUnlock::HEADER);
    }
}
