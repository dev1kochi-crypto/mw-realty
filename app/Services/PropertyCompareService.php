<?php

namespace App\Services;

/**
 * Builds the /compare page: up to five listings in one shape, whatever emirate they are in — price,
 * size, specs, amenities, nearest places, what it costs to buy (or move in) under that emirate's
 * fees, the market estimates from PropertyInsightService and who may own it. Every amount is in AED;
 * the page converts to the visitor's currency.
 *
 * Built on the cached property page payload (PropertyPageService) so a listing reads the same here
 * as on its own page.
 */
class PropertyCompareService
{
    private const PERIODS_PER_YEAR = ['yearly' => 1, 'monthly' => 12, 'weekly' => 52, 'daily' => 365];

    private const VAT = 1.05;

    public function __construct(
        private readonly PropertyPageService $propertyPage,
        private readonly PropertyInsightService $insights,
    ) {
    }

    /** Clean, de-duplicated slugs from "a,b,c" or an array, capped at max_items. */
    public static function slugs(string|array|null $input): array
    {
        $slugs = is_array($input) ? $input : explode(',', (string) $input);
        $slugs = array_values(array_unique(array_filter(array_map(fn ($s) => trim((string) $s), $slugs), fn ($s) => $s !== '' && strlen($s) <= 255)));

        return array_slice($slugs, 0, (int) config('property_compare.max_items'));
    }

    /**
     * @return array{properties: array, notes: array}
     */
    public function build(array $slugs, string $lang): array
    {
        $properties = [];
        foreach ($slugs as $slug) {
            $detail = $this->propertyPage->getPropertyDetail($lang, $slug);
            if ($detail) {
                $properties[] = $this->item($detail, $this->insights->forSlug($slug));
            }
        }

        $c = config('property_compare');

        return [
            'properties' => $properties,
            'notes' => [
                'fx_spread_pct' => (float) $c['fx_spread_pct'],
                'golden_visa_min' => (float) $c['golden_visa_min_aed'],
                'non_resident_max_ltv_pct' => (float) $c['non_resident_max_ltv_pct'],
                'uae_tax' => $c['uae_tax_note'],
                'home_country' => $c['home_country_notes'],
            ],
        ];
    }

    private function item(array $d, ?array $insights): array
    {
        $isRent = $d['listing_type'] === 'rent';
        $price = $d['price_value'];
        $annualRent = $isRent && $price ? $price * (self::PERIODS_PER_YEAR[$d['rental_period'] ?? ''] ?? 1) : null;
        // Rent is compared per year (whatever period it's advertised in), sale on the price.
        $amount = $isRent ? $annualRent : $price;
        $sqft = $d['sqft'];
        $emirate = $d['emirate'] && config("property_compare.purchase_fees.{$d['emirate']}") ? $d['emirate'] : config('property_compare.default_emirate');

        $costs = $amount ? ($isRent ? $this->moveInCosts($amount, (bool) $d['furnished'], $d['pricing']['security_deposit'] ?? null) : $this->purchaseCosts($emirate, $amount)) : null;
        $market = $this->market($isRent, $price, $sqft, $insights);

        return [
            'id' => $d['id'],
            'slug' => $d['slug'],
            'name' => $d['name'],
            'image' => $d['images'][0] ?? null,
            'url' => "/property-details/{$d['slug']}",
            'listing_type' => $isRent ? 'rent' : 'sale',
            'listing_type_label' => $d['listing_type_label'],
            'type' => $d['type'],
            'completion_status' => $d['completion_status_label'],
            'location' => $d['location'],
            'emirate' => $d['emirate'],
            'emirate_label' => $d['emirate_label'],
            'price' => $price,
            'rental_period' => $d['rental_period_label'],
            'annual_rent' => $annualRent,
            'sqft' => $sqft,
            'price_psf' => $amount && $sqft ? round($amount / $sqft, 2) : null,
            'beds' => $d['beds'],
            'baths' => $d['baths'],
            'furnishing' => $d['furnishing'],
            'floor' => $d['floor'],
            'parking' => $d['parking'],
            'year_built' => $d['year_built'],
            'developer' => $d['developer'],
            'view' => $d['view'],
            'amenities' => collect($d['amenities'])->pluck('label')->merge(collect($d['easy_access'])->pluck('label'))->unique()->values()->all(),
            'nearby' => $this->nearest($d['nearby'] ?? []),
            'costs' => $costs,
            // Cash needed up front: price + purchase costs, or first year's rent + move-in costs.
            'total_outlay' => $costs ? round($amount + $costs['total']) : null,
            'market' => $market,
            'ownership' => $isRent ? null : config("property_compare.ownership.{$emirate}"),
            'golden_visa' => !$isRent && $price && $price >= (float) config('property_compare.golden_visa_min_aed'),
        ];
    }

    /** The emirate's one-off buying fees on `price`, each listed, plus the total. */
    private function purchaseCosts(string $emirate, float $price): array
    {
        $items = collect(config("property_compare.purchase_fees.{$emirate}"))->map(fn ($fee) => [
            'key' => $fee['key'],
            'label' => $fee['label'],
            'amount' => round(($price * $fee['pct'] / 100 + $fee['fixed']) * (!empty($fee['vat']) ? self::VAT : 1)),
        ])->values();

        return ['items' => $items->all(), 'total' => $items->sum('amount')];
    }

    /** Agency fee + refundable deposit on a year's rent (the listing's own deposit when it states one). */
    private function moveInCosts(float $annualRent, bool $furnished, ?float $deposit): array
    {
        $c = config('property_compare.rent_fees');
        $items = [
            ['key' => 'agency', 'label' => "Agency fee ({$c['agency_pct']}% of annual rent + VAT)", 'amount' => round($annualRent * $c['agency_pct'] / 100 * self::VAT)],
            ['key' => 'deposit', 'label' => 'Security deposit (refundable)', 'amount' => round($deposit ?: $annualRent * ($furnished ? $c['deposit_furnished_pct'] : $c['deposit_pct']) / 100)],
        ];

        return ['items' => $items, 'total' => array_sum(array_column($items, 'amount'))];
    }

    /**
     * Estimates from the listing's Market & Investment panel, plus running costs and a net yield for
     * sale listings (estimated rent less vacancy, service charge and maintenance, over the price).
     */
    private function market(bool $isRent, ?float $price, ?int $sqft, ?array $insights): ?array
    {
        if (!$insights) {
            return null;
        }
        $estimates = $insights['estimates'];
        $assumptions = $insights['assumptions'];
        $rent = $estimates['annual_rent']['amount'] ?? null;
        $serviceCharge = !$isRent && $sqft ? round($assumptions['service_charge_psf'] * $sqft) : null;

        $netYield = null;
        if ($rent && $price) {
            $net = $rent * (1 - $assumptions['vacancy_pct'] / 100) - ($serviceCharge ?? 0) - $price * $assumptions['maintenance_pct'] / 100;
            $netYield = round($net / $price * 100, 2);
        }
        $ratio = $estimates['price_ratio']['ratio'] ?? null;
        $grossYield = $estimates['gross_yield']['pct'] ?? null;
        $plausible = fn ($pct) => $pct !== null && $pct <= (float) config('property_compare.max_plausible_yield_pct') ? $pct : null;

        return [
            'estimated_value' => $estimates['value']['amount'] ?? null,
            'estimated_annual_rent' => $rent,
            'gross_yield_pct' => $plausible($grossYield),
            'net_yield_pct' => $plausible($grossYield) === null ? null : $netYield,
            'vs_market_pct' => $ratio !== null ? round(($ratio - 1) * 100, 1) : null,
            'growth_pct' => $estimates['growth']['pct'] ?? null,
            'service_charge_annual' => $serviceCharge,
            'headline' => $insights['assessment']['headline'] ?? null,
        ];
    }

    /** The closest place of each nearby type: { metro: { label, name, distance_km } }. */
    private function nearest(array $groups): array
    {
        $out = [];
        foreach ($groups as $group) {
            $place = collect($group['places'])->filter(fn ($p) => $p['distance_km'] !== null)->sortBy('distance_km')->first();
            if ($place) {
                $out[$group['key']] = ['label' => $group['label'], 'name' => $place['name'], 'distance_km' => $place['distance_km']];
            }
        }

        return $out;
    }
}
