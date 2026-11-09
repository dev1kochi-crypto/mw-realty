<?php

return [
    /*
    | Property Details › Buyer Demand and CRM › Market Demand (App\Services\MarketDemand\*).
    |
    | Built only from activity on MW Realty itself (searches, saved searches, listing views,
    | favourites, contact clicks and enquiry forms), aggregated per day into market_demand_daily by
    | `php artisan market-demand:aggregate` (hourly). Counts are different browsers per day, never
    | people, and nothing that identifies a visitor is kept in the aggregate.
    */

    // "This month" vs "the month before": two windows of this many days.
    'window_days' => 30,

    // Months on the demand-over-time chart.
    'trend_months' => 6,

    // Amenity ranking looks back further — amenity filters are rarer than plain searches.
    'amenity_days' => 90,

    // Weights that turn activity into one interest score (an enquiry says more than a view).
    'weights' => [
        'searches' => 1,
        'saved_searches' => 3,
        'views' => 1,
        'favorites' => 3,
        'enquiries' => 5,
    ],

    /*
    | Privacy / significance thresholds.
    |   min_activity — interest score a segment needs before it gets a High / Medium / Low rating,
    |                  and an area before its shares (bedrooms, price bands) are shown.
    |   min_change   — each window needs at least this many before a % change is quoted.
    |   min_count    — smaller counts are shown as "fewer than N" so no single visitor stands out.
    */
    'min_activity' => (int) env('MARKET_DEMAND_MIN_ACTIVITY', 10),
    'min_change' => 5,
    'min_count' => 3,

    // Interest per listing in the segment ÷ the same across the city (all types, same purpose).
    'high_ratio' => 1.3, // at or above → High
    'low_ratio' => 0.7,  // below → Low

    // A bedroom count / price band is "underserved" when its share of demand is at least this
    // multiple of its share of listings.
    'underserved_ratio' => 1.25,
    // …and at least this % of all interest, so a handful of stray searches doesn't flag a band.
    'underserved_min_share' => 5,

    // Highest bedroom count kept as its own value; more are grouped as "N+".
    'max_bedrooms' => 6,

    /*
    | Price bands, AED (rent = per year). Each value is a band's lower bound; the last band is open.
    */
    'price_bands' => [
        'sale' => [0, 750000, 1500000, 2500000, 4000000, 7000000, 12000000],
        'rent' => [0, 50000, 90000, 130000, 200000, 300000, 500000],
    ],

    // How long one AI summary is reused for the same segment and language (seconds).
    'summary_cache_ttl' => 6 * 3600,
];
