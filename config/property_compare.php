<?php

return [
    /*
    | Property comparison (/compare — App\Services\PropertyCompareService).
    |
    | Every amount is in AED, like every price on the site; the page converts to the visitor's
    | currency. Fees and rules are typical published figures — estimates for comparing listings,
    | not a quote — so keep them current here when the authorities change them.
    */
    'max_items' => 5,
    'min_items' => 2,

    /*
    | One-off costs of buying, per emirate (the `emirate` listing field). Each item is pct of the
    | price + a fixed AED amount, with VAT (5%) on top when `vat` is true.
    */
    'purchase_fees' => [
        'dubai' => [
            ['key' => 'land_department', 'label' => 'Dubai Land Department fee (4% + AED 580)', 'pct' => 4, 'fixed' => 580],
            ['key' => 'trustee', 'label' => 'Registration trustee fee (AED 4,000 + VAT)', 'pct' => 0, 'fixed' => 4000, 'vat' => true],
            ['key' => 'agency', 'label' => 'Real estate agency fee (2% + VAT)', 'pct' => 2, 'fixed' => 0, 'vat' => true],
        ],
        'abu_dhabi' => [
            ['key' => 'transfer', 'label' => 'Abu Dhabi transfer fee (2%)', 'pct' => 2, 'fixed' => 0],
            ['key' => 'agency', 'label' => 'Real estate agency fee (2% + VAT)', 'pct' => 2, 'fixed' => 0, 'vat' => true],
        ],
        'northern_emirates' => [
            ['key' => 'registration', 'label' => 'Land department registration fee (about 4%, varies by emirate)', 'pct' => 4, 'fixed' => 0],
            ['key' => 'agency', 'label' => 'Real estate agency fee (2% + VAT)', 'pct' => 2, 'fixed' => 0, 'vat' => true],
        ],
    ],
    'default_emirate' => 'dubai', // listings without an emirate are costed as Dubai

    // Moving into a rental: agency fee (% of the annual rent + VAT) and the usual refundable deposit.
    'rent_fees' => [
        'agency_pct' => 5,
        'deposit_pct' => 5,            // unfurnished
        'deposit_furnished_pct' => 10, // furnished
    ],

    /*
    | Who may own what, per emirate — shown as the "Ownership" row and given to the AI summary.
    */
    'ownership' => [
        'dubai' => 'Foreign buyers can own freehold in Dubai\'s designated freehold areas.',
        'abu_dhabi' => 'Foreign buyers can own freehold in Abu Dhabi\'s designated investment zones.',
        'northern_emirates' => 'Foreign ownership is limited to designated developments; rules differ between Sharjah, Ajman and Ras Al Khaimah.',
    ],

    /*
    | Cross-border notes. `fx_spread_pct` is the typical bank margin when converting the buyer's
    | money into AED — only shown when the page is in another currency.
    */
    'fx_spread_pct' => 1.5,
    'golden_visa_min_aed' => 2000000, // property value that qualifies for the 10-year Golden Visa
    'non_resident_max_ltv_pct' => 60, // UAE Central Bank: non-residents borrow at most 60% of the value
    'uae_tax_note' => 'The UAE has no annual property tax, no capital gains tax and no income tax on rent for individual owners.',

    // Extra notes for buyers paying in a particular currency (keyed by currency code).
    'home_country_notes' => [
        'INR' => 'Indian residents can send up to USD 250,000 abroad per person per financial year under the RBI\'s Liberalised Remittance Scheme; TCS may be collected on larger remittances.',
        'GBP' => 'UK residents are usually taxed in the UK on overseas rental income and gains, even though the UAE charges none.',
        'USD' => 'US citizens and residents are taxed on worldwide income, so UAE rent and sale gains must still be reported to the IRS.',
        'EUR' => 'Most EU countries tax residents on overseas rental income and gains — check your country\'s rules and any tax treaty with the UAE.',
    ],

    // A yield above this comes from a mistyped price (e.g. AED 1), not the market — it's left out.
    'max_plausible_yield_pct' => 30,

    // How long a visitor's unlock lasts (days) once they leave their contact details on /compare —
    // until then costs, total cash needed and the investment figures are hidden (App\Services\CompareUnlock).
    'unlock_days' => 365,

    // How long one AI summary is reused for the same listings + currency + language (seconds).
    'summary_cache_ttl' => 3600,
];
