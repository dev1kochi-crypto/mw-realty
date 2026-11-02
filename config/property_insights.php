<?php

return [
    /*
    | Property Details › Market & Investment (App\Services\PropertyInsightService).
    |
    | "Verified" figures come from deals closed on the platform (Mark as sold / rented, with the title
    | deed + DLD contract uploaded); "asking" figures from live listings and are shown as estimates.
    | A figure needs at least `min_sample` listings / deals behind it, otherwise it is left out.
    */
    'min_sample' => (int) env('PROPERTY_INSIGHTS_MIN_SAMPLE', 3),

    // How far back closed deals count, and how many quarters the price trend chart shows.
    'verified_months' => 24,
    'trend_quarters' => 8,

    // Comparable listings: same type, price within ±band, bedrooms within ±beds.
    'comparable_price_band' => 0.30,
    'comparable_beds' => 1,

    // Assessment badge thresholds.
    'high_yield_pct' => 7.0,      // gross rental yield at or above → "High yield potential"
    'low_yield_pct' => 4.5,       // below → "Lower yield"
    'below_market_ratio' => 0.92, // price / sq.ft at or below 92% of the area median → "Priced below market"
    'above_market_ratio' => 1.12, // at or above 112% → "Priced above market"
    'strong_growth_pct' => 5.0,   // year-on-year price / sq.ft change at or above → "Strong capital growth"

    /*
    | Starting assumptions for the investment calculator — every one can be changed by the visitor.
    | Service charges are AED per sq.ft per year (typical Dubai ranges: apartments 15–25, villas 3–8).
    */
    'service_charge_psf' => [
        'apartment' => 18, 'penthouse' => 22, 'condo' => 18, 'villa' => 5, 'townhouse' => 5,
        'office' => 20, 'retail-shop' => 22, 'showroom' => 20, 'warehouse' => 6,
        'default' => 12,
    ],
    'maintenance_pct' => 1.0, // of the price, per year
    'vacancy_pct' => 5.0,     // of the year the unit sits empty between tenants
];
