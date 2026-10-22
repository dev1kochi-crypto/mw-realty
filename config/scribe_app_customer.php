<?php

use App\Support\ApiDocs\AppScreens;
use Knuckles\Scribe\Config\Defaults;

/*
|--------------------------------------------------------------------------
| Customer mobile app — API docs (/docs/app-customer)
|--------------------------------------------------------------------------
| Only the endpoints the app's first-release screens call, filed under the app's tabs. Everything
| else (headers, auth, examples) is the frontend docs' config (config/scribe.php). Endpoint
| descriptions come from the controllers' docblocks, as in the full docs.
| Regenerate with `php artisan docs:generate`.
*/
$base = require __DIR__ . '/scribe.php';

$screens = [
    [
        'group' => 'Entry',
        'description' => 'First launch: pick a language, then continue as a guest or sign in. Guests can search, view properties and send enquiries; Saved, Activity and Account need a signed-in user.',
        'screens' => [
            'Language & app copy' => [
                'GET /api/languages',
                'GET /api/static-translations',
                'GET /api/currencies',
            ],
            'Sign up / sign in' => [
                'POST /api/auth/register',
                'POST /api/auth/verify-otp',
                'POST /api/auth/resend-otp',
                'POST /api/auth/login',
                'POST /api/auth/google',
                'POST /api/auth/forgot-password',
                'POST /api/auth/reset-password',
                'POST /api/auth/logout',
                'GET /api/customer/session',
            ],
        ],
    ],
    [
        'group' => 'Home',
        'description' => 'Buy / Rent / Commercial / New Projects switch, the search bar, recently viewed and latest listings. **Recently viewed** has no endpoint: keep the last viewed property cards on the phone.',
        'screens' => [
            'Home' => [
                'GET /api/home',
                'GET /api/home/new-projects',
            ],
        ],
        'also' => [
            'GET /api/location-suggestions',
            'GET /api/properties',
            'GET /api/commercial',
        ],
    ],
    [
        'group' => 'Search',
        'description' => 'Location search, the results list and map, filters, and the property details screen (contact, viewing, mortgage).',
        'screens' => [
            'Results, map & filters' => [
                'GET /api/location-suggestions',
                'GET /api/property-filters',
                'GET /api/search-filters/{page}',
                'GET /api/properties',
                'GET /api/commercial',
                'GET /api/properties/map',
            ],
            'Property details' => [
                'GET /api/properties/{slug}',
                'POST /api/leads/capture',
                'POST /api/track/lead-click',
                'POST /api/leads/viewing',
                'GET /api/calculators/{key}',
            ],
        ],
        'also' => [
            'POST /api/customer/wishlist/{property}',
            'POST /api/customer/saved-searches',
        ],
    ],
    [
        'group' => 'Saved',
        'description' => 'Saved properties and saved searches. Signed-in users only.',
        'screens' => [
            'Saved properties' => [
                'GET /api/customer/wishlist',
                'POST /api/customer/wishlist/{property}',
            ],
            'Saved searches' => [
                'POST /api/customer/saved-searches',
                'DELETE /api/customer/saved-searches/{savedSearch}',
            ],
        ],
        'also' => [
            'GET /api/customer/me',
            'GET /api/properties',
        ],
    ],
    [
        'group' => 'Activity',
        'description' => 'Enquiries and viewing requests the user sent, with their status, and the recent activity feed. Signed-in users only.',
        'screens' => [
            'Activity' => [
                'GET /api/customer/me',
            ],
        ],
    ],
    [
        'group' => 'Account',
        'description' => 'Profile, language and settings; help, privacy and terms; delete account.',
        'screens' => [
            'Profile & settings' => [
                'POST /api/customer/settings',
                'DELETE /api/customer/account',
            ],
            'Help, privacy & terms' => [
                'GET /api/legal/{key}',
                'GET /api/site-information',
            ],
        ],
        'also' => [
            'GET /api/customer/me',
            'GET /api/languages',
            'POST /api/auth/logout',
        ],
    ],
    [
        'group' => 'Background tracking',
        'description' => 'Sent in the background, not tied to one tab: screen views, time on a listing and listing impressions. They feed the agents\' listing stats and lead insights.',
        'screens' => [
            'Tracking' => [
                'POST /api/track/page',
                'POST /api/track/page/{event}/time',
                'POST /api/track/impressions',
            ],
        ],
    ],
];

$intro = <<<'INTRO'
    Only the endpoints the **customer app's first release** calls, filed under the app's tabs:
    **Entry → Home → Search → Saved → Activity → Account**, plus **Background tracking**. Each tab's
    introduction also lists the *connected* endpoints it calls that are documented under another tab.
    The complete website API is at [/docs/frontend](/docs/frontend).

    ## Not in the API yet

    These parts of the app map have no endpoint yet. Agree on them with the backend team before building:

    | Screen | Missing |
    |---|---|
    | Entry | Sign-in by **phone OTP** — sign-up and sign-in are by email (code by email) or Google. |
    | Home | **Recently viewed** — keep it on the phone. |
    | Saved | **Price-drop badges** on saved properties and **alert settings** for saved searches. |
    | Activity | **Notifications feed** (and push notifications). |
    | Account | **Notification settings**. |


    INTRO;

return array_replace($base, [
    'title' => 'MW Realty — Customer App API',
    'description' => 'The endpoints the MW Realty customer app calls, by app tab: Entry, Home, Search, Saved, Activity, Account.',
    'intro_text' => $intro . str_replace('the **User Account** endpoints', 'Saved, Activity and Account', $base['intro_text']),

    'app_screens' => $screens,

    'routes' => [
        [
            'match' => ['prefixes' => [], 'domains' => ['*']],
            // Every endpoint in the map (inline: Scribe is a dev dependency, so no class calls at config load).
            'include' => array_values(array_unique(array_merge(...array_map(fn ($tab) => array_merge(...array_values($tab['screens'])), $screens)))),
            'exclude' => [],
        ],
    ],

    'static' => ['output_path' => 'public/docs/_scribe/app-customer'],

    'groups' => [
        'default' => 'Other',
        'order' => array_column($screens, 'group'),
    ],

    'strategies' => array_replace($base['strategies'], [
        'metadata' => [...Defaults::METADATA_STRATEGIES, AppScreens::class],
    ]),
]);
