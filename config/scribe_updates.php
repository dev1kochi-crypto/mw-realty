<?php

use App\Support\ApiDocs\AppScreens;
use Knuckles\Scribe\Config\Defaults;

/*
|--------------------------------------------------------------------------
| Latest updates — API docs (/docs/updates)
|--------------------------------------------------------------------------
| Only the endpoints added or changed for the latest features (AI matching & chat, trust engine,
| investment intelligence, cross-border compare, listing automation, market demand, the new
| Property Details sections and the calculators), filed under the feature they belong to — so the
| app / frontend team sees what is new without reading the full docs.
| Same screen-map mechanism as the mobile app docs (App\Support\ApiDocs\AppScreens): each feature
| is a 'group', its 'screens' are documented there, 'also' lists endpoints documented under
| another feature. Endpoint descriptions come from the controllers' docblocks.
| Regenerate with `php artisan docs:generate`.
*/
$base = require __DIR__ . '/scribe.php';

$features = [
    [
        'group' => 'AI chat & property matching',
        'description' => <<<'MD'
        **Features 1 & 5 — AI property matching engine + conversational assistant.** The buyer describes what they want in their own words ("a two-bedroom apartment in Abu Dhabi, near schools, with rental income potential, a manageable service charge and a 20-minute commute"); the assistant turns it into a brief (`requirements`) and returns real listings **ranked** against it, each card with a `match` (`score` 0–100, `reasons`, `misses`). Near misses (one bedroom more, a little over budget) still show, lower down, with the reason.

        **Flow**

        1. Chat screen opens → `GET /api/chatbot/session` (send `X-Device-Id`; a customer token identifies the user). Show `languages` as "Choose your language".
        2. Each message → `POST /api/chatbot/message` with `message`, `conversation_id`, `lang` (listing text) and `language` (chat language). Render `reply`, then `properties` as cards with their `match`.
        3. When a reply (or a `422`) has `details_required: true`, show the details form → `POST /api/chatbot/start`, then resend the message.
        4. "Clear chat" → `POST /api/chatbot/reset`.

        **What is scored:** budget, bedrooms, nearby places (school, metro, mall, beach, … — the places tagged on the listing), investment purpose, size, bathrooms, furnishing. Service charge, financing and commute are captured in `requirements` for the agent but are not in the listing data, so they are not scored. A loosely-worded place ("the Ranches area") falls back to its meaningful words, so it still finds Arabian Ranches.

        **Server setup (backend, `.env`):** `CHATBOT_PROVIDER=groq` (default) or `gemini`; `GROQ_API_KEY` / `GROQ_MODEL` (default `openai/gpt-oss-120b`) or `GEMINI_API_KEY` / `GEMINI_MODEL`; optional `CHATBOT_NAME` (persona, default "Noor"). Without a key the chat answers with the `unavailable` text instead of failing. The chat languages and their fixed strings are in `config/chatbot.php`. Limits: 8 messages/min and 150/day per IP.

        **Agent side:** the captured brief and the transcript appear on the website lead in the CRM (`GET /api/crm/website-leads/{id}/insights`).
        MD,
        'screens' => [
            'Chat' => [
                'GET /api/chatbot/session',
                'POST /api/chatbot/message',
                'POST /api/chatbot/start',
                'POST /api/chatbot/reset',
            ],
            'Buyer brief for the agent (CRM)' => [
                'GET /api/crm/website-leads/{websiteLead}/insights',
                'GET /api/crm/website-leads/{websiteLead}/insights/summary',
            ],
        ],
    ],
    [
        'group' => 'Property Details — new sections',
        'description' => <<<'MD'
        The new sections of the Property Details page and the endpoint behind each one. Load the page with `GET /api/properties/{slug}`, then the sections **separately** (they are slower and can show a loading state):

        | Section | Endpoint | Shown for |
        |---|---|---|
        | Mortgage / Investment calculator (two tabs) | none — calculated on the device, see **Calculators**; the Investment tab is seeded from `insights` | sale listings |
        | Rental costs (move-in costs) | none — calculated on the device, see **Calculators** | rent listings |
        | Market & investment insights (price vs area, estimated rent, gross / net yield, growth, trend chart) | `GET /api/properties/{slug}/insights` | all |
        | Buyer demand (+ AI written overview) | `GET /api/properties/{slug}/demand`, `GET /api/properties/{slug}/demand/summary` | all |
        | Nearby places map | `nearby` and `map` in `GET /api/properties/{slug}` | when set |
        | Add to compare | see **Cross-border compare** | all |

        The market figures in `insights` carry a `source` — `verified` (from recorded transactions, label "Verified · n deals") or `asking` (from listing prices, label "Estimate · n listings") — and a `count`. Show it, so estimates are never presented as verified data.
        MD,
        'screens' => [
            'Page' => [
                'GET /api/properties/{slug}',
            ],
            'Market & investment insights' => [
                'GET /api/properties/{slug}/insights',
            ],
            'Buyer demand' => [
                'GET /api/properties/{slug}/demand',
                'GET /api/properties/{slug}/demand/summary',
            ],
        ],
        'also' => [
            'GET /api/compare',
            'GET /api/currencies',
        ],
    ],
    [
        'group' => 'Calculators',
        'description' => <<<'MD'
        **Feature 3 — investment intelligence.** The calculators run **on the device**: the API gives only the page content (`GET /api/calculators`, `GET /api/calculators/{key}`) and, on Property Details, the market figures that seed them (`GET /api/properties/{slug}/insights`). Calculate in AED and convert for display with `GET /api/currencies`. The website's code is in `resources/js/components/calculators/finance.js` and `resources/js/components/insights/investment.js` — port it as-is so the app shows the same numbers.

        ### 1. Mortgage calculator (Property Details "Mortgage" tab, and `/calculators/mortgage`)

        Inputs: price (from the listing), residency, down payment %, interest rate (1–10%), term (max 25 years).

        | Residency | Min. down payment ≤ AED 5M | > AED 5M | Starting rate |
        |---|---|---|---|
        | UAE National | 15% | 25% | 4.24% |
        | UAE Resident | 20% | 30% | 3.75% |
        | Non-resident | 40% | 40% | 4.19% |

        Down payment max is 80%. Changing residency resets the down payment to its minimum and the rate to its starting rate.

        ```
        loan     = price × (1 − down% / 100)
        r        = rate% / 100 / 12          n = years × 12
        monthly  = loan × r × (1+r)^n / ((1+r)^n − 1)      (loan / n when r = 0)
        total    = monthly × n               interest = total − loan
        ```

        Purchase costs (Dubai), shown under it — mortgage items only when there is a loan:

        | Item | Amount |
        |---|---|
        | Land Department fee | 4% of price + AED 580 |
        | Registration trustee | AED 4,000 + 5% VAT |
        | Mortgage registration | 0.25% of loan + AED 10 |
        | Agency fee | 2% of price + 5% VAT |
        | Bank arrangement | 1% of loan + 5% VAT |
        | Valuation | AED 3,000 + 5% VAT |

        ### 2. Investment calculator (Property Details "Investment" tab, sale listings)

        Starting values: **rent** = `insights.estimates.annual_rent.amount` (else 6% of the price); **service charges** = size (sq.ft) × `insights.assumptions.service_charge_psf`; **maintenance %** and **vacancy %** from `insights.assumptions` (defaults 12 AED/sq.ft, 1%, 5%). Every value can be changed; plus residency / down payment / rate / term as in the mortgage calculator, and holding period (default 5 years).

        ```
        loan          = price × (1 − down%/100)        down = price − loan
        fees          = purchase costs above            upfront = down + fees
        monthlyLoan   = monthly(loan, rate, years)
        effectiveRent = rent × (1 − vacancy%/100)
        maintenance   = price × maintenance%/100
        runningCosts  = serviceCharges + maintenance                (a year)
        NOI           = effectiveRent − runningCosts
        cashFlow/yr   = NOI − monthlyLoan × 12                       (show /12 as monthly)
        grossYield    = rent / price × 100
        netYield      = NOI / (price + fees) × 100
        cashOnCash    = cashFlow/yr / upfront × 100
        -- over the holding period (hold years) --
        loanPaid      = monthlyLoan × min(hold, years) × 12
        loanBalance   = balance after hold×12 instalments
        loanInterest  = loanPaid − (loan − loanBalance)
        totalCost     = upfront + loanPaid + runningCosts × hold
        rentIncome    = effectiveRent × hold        netCost = totalCost − rentIncome
        ```

        **Financing scenarios** table: the same calculation for down payments of the minimum, 40%, 60%, 100% (cash) and the user's own choice.

        ### Rental costs (rent listings)

        Annual rent = price × periods per year (yearly 1, monthly 12, weekly 52, daily 365). Security deposit = the listing's `pricing.security_deposit`, else 5% of the annual rent (10% furnished); agency fee 5% (editable) + VAT; Ejari / Tawtheeq AED 220; utility deposit AED 2,000 (apartment) / 4,000 (villa, townhouse); housing fee 5% of the rent in Dubai, 3% in Abu Dhabi. Refundable: the two deposits. Year one = rent + housing fee + move-in costs − refundable.

        The other two calculator pages (`eligibility`, `rent-vs-buy`, `refinance`) use the same `finance.js` helpers; `settings` in `GET /api/calculators/{key}` holds their admin-editable options (e.g. the refinance lender list).
        MD,
        'screens' => [
            'Calculator pages' => [
                'GET /api/calculators',
                'GET /api/calculators/{key}',
            ],
        ],
        'also' => [
            'GET /api/properties/{slug}/insights',
            'GET /api/currencies',
        ],
    ],
    [
        'group' => 'Cross-border compare',
        'description' => <<<'MD'
        **Feature 4 — cross-border property intelligence.** Compare 2–5 listings in any UAE emirate side by side in one shape: price, size, price per sq.ft, buying costs under each emirate's fees (move-in costs for rentals), market estimates, ownership rules, Golden Visa — with a written AI summary in the buyer's currency.

        The money and investment figures are **locked** until the visitor leaves contact details: `POST /api/contact` with `source=compare` returns a token; send it as `X-Compare-Unlock` on both compare endpoints. Amounts in `GET /api/compare` are AED — convert with `GET /api/currencies`; the summary takes `currency` itself.
        MD,
        'screens' => [
            'Compare' => [
                'GET /api/compare',
                'GET /api/compare/summary',
                'POST /api/contact',
            ],
            'Currencies' => [
                'GET /api/currencies',
            ],
        ],
    ],
    [
        'group' => 'Trust engine (CRM)',
        'description' => <<<'MD'
        **Feature 2 — property authenticity and trust engine.** Automated screening flags listings for a **human review** — it never claims a listing is verified by AI: the same unit already listed by another agency (duplicate photos via image hashing, shown side by side with the price spread; the first listing stays live, newer ones wait), price far off the area, advert details not matching the permit, resubmitted listings. Super Admin approves or rejects each one. Permits are checked when a listing is created (`POST /api/crm/properties/permit/validate`) and reviewed in the permit queue.

        CRM endpoints: sign in with `POST /api/crm/auth/login` and send `Authorization: Bearer {token}`.
        MD,
        'screens' => [
            'Listing review queue (Super Admin)' => [
                'GET /api/crm/listing-review',
                'GET /api/crm/listing-review/{id}',
                'POST /api/crm/listing-review/{id}/approve',
                'POST /api/crm/listing-review/{id}/reject',
            ],
            'Permits' => [
                'POST /api/crm/properties/permit/validate',
                'GET /api/crm/listing-permits',
                'GET /api/crm/listing-permits/{id}',
                'POST /api/crm/listing-permits/{id}/approve',
                'POST /api/crm/listing-permits/{id}/take-down',
            ],
        ],
    ],
    [
        'group' => 'Listing automation (CRM)',
        'description' => <<<'MD'
        **Feature 6 — agency listing automation.** Agencies bulk-upload their inventory from a spreadsheet (or another CRM's export): upload → the columns are matched to our fields → confirm → the import runs in the background (poll its progress) → download the file back with an Import Status / Remarks column per row. Descriptions are standardised and missing information reported per row. In the listing form, **Write with AI** drafts the title / description from the details and **Auto-translate** fills the other languages.

        CRM endpoints: `Authorization: Bearer {token}` from `POST /api/crm/auth/login`.
        MD,
        'screens' => [
            'Spreadsheet import' => [
                'GET /api/crm/properties/imports/template',
                'POST /api/crm/properties/imports',
                'POST /api/crm/properties/imports/{propertyImport}/start',
                'GET /api/crm/properties/imports/{propertyImport}',
                'GET /api/crm/properties/imports/latest',
                'GET /api/crm/properties/imports',
                'GET /api/crm/properties/imports/{propertyImport}/result',
            ],
            'Listing form helpers' => [
                'POST /api/crm/properties/write-copy',
                'POST /api/crm/properties/translate',
            ],
        ],
    ],
    [
        'group' => 'Market demand',
        'description' => <<<'MD'
        **Feature 7 — property demand and market analytics.** Anonymised, aggregated marketplace demand (searches, views, saves, enquiries vs listing supply) — never an individual lead or visitor. On the website it is the **Buyer demand** section of Property Details; in the CRM it is the **Market Demand** page for agencies and developers (where buyers look, underserved bedroom counts and price bands, month-by-month change), a plan feature (`market_demand`).
        MD,
        'screens' => [
            'CRM Market Demand' => [
                'GET /api/crm/market-demand',
                'GET /api/crm/market-demand/areas',
            ],
        ],
        'also' => [
            'GET /api/properties/{slug}/demand',
            'GET /api/properties/{slug}/demand/summary',
        ],
    ],
    [
        'group' => 'Performance insights (CRM)',
        'description' => 'Other recent CRM updates: how each listing performs (views, impressions, enquiries, insight views), the agency\'s agents compared, and lead insights.',
        'screens' => [
            'Listings' => [
                'GET /api/crm/property-insights',
                'GET /api/crm/properties/{id}/insights',
            ],
            'Agents' => [
                'GET /api/crm/agent-insights',
                'GET /api/crm/agent-insights/{id}',
            ],
            'Leads' => [
                'GET /api/crm/lead-insights',
                'GET /api/crm/leads/{id}/insights',
                'GET /api/crm/leads/{id}/insights/summary',
            ],
        ],
    ],
];

$intro = <<<'INTRO'
    **Only the endpoints added or changed in the latest updates**, filed by feature. Each feature's
    introduction explains how its endpoints fit together and lists *connected* endpoints documented
    under another feature. Everything else is unchanged — the complete references are
    [/docs/frontend](/docs/frontend) (website & customer app) and [/docs/crm](/docs/crm) (CRM).

    | Feature | Where |
    |---|---|
    | 1 & 5 · AI property matching + conversational assistant | **AI chat & property matching** |
    | 2 · Authenticity and trust engine | **Trust engine (CRM)** |
    | 3 · Investment intelligence | **Property Details — new sections**, **Calculators** |
    | 4 · Cross-border property intelligence | **Cross-border compare** |
    | 6 · Agency listing automation | **Listing automation (CRM)** |
    | 7 · Demand and market analytics | **Market demand** |
    | Other | **Performance insights (CRM)** |

    Endpoints under `/api/crm/…` need a CRM token (`POST /api/crm/auth/login`, then
    `Authorization: Bearer {token}`); the rest are the public website / app API described below.

    ## Not in the API yet

    | What | Note |
    |---|---|
    | Recording that a visitor opened the insights section | The website posts to `/track/insight-view` (web route, session cookie) — no `/api` endpoint for the app yet. |
    | Calculations | By design there is no calculate endpoint — see **Calculators** for the formulas. |


    INTRO;

return array_replace($base, [
    'title' => 'MW Realty — Latest API Updates',
    'description' => 'Only the endpoints added or changed in the latest updates: AI chat & matching, Property Details sections, calculators, cross-border compare, trust engine, listing automation, market demand.',
    'intro_text' => $intro . $base['intro_text'],

    'app_screens' => $features,

    'routes' => [
        [
            'match' => ['prefixes' => [], 'domains' => ['*']],
            // Every endpoint in the map (inline: Scribe is a dev dependency, so no class calls at config load).
            'include' => array_values(array_unique(array_merge(...array_map(fn ($f) => array_merge(...array_values($f['screens'])), $features)))),
            'exclude' => [],
        ],
    ],

    'static' => ['output_path' => 'public/docs/_scribe/updates'],

    'groups' => [
        'default' => 'Other',
        'order' => array_column($features, 'group'),
    ],

    'strategies' => array_replace($base['strategies'], [
        'metadata' => [...Defaults::METADATA_STRATEGIES, AppScreens::class],
    ]),
]);
