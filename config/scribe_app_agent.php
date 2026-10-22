<?php

use App\Support\ApiDocs\AppScreens;
use Knuckles\Scribe\Config\Defaults;

/*
|--------------------------------------------------------------------------
| Agent mobile app — API docs (/docs/app-agent)
|--------------------------------------------------------------------------
| Only the CRM endpoints the agent app's first-release screens call, filed under the app's tabs.
| Everything else (headers, auth, examples) is the CRM docs' config (config/scribe_crm.php).
| Endpoint descriptions come from the controllers' docblocks, as in the full docs.
| Regenerate with `php artisan docs:generate`.
*/
$base = require __DIR__ . '/scribe_crm.php';

$screens = [
    [
        'group' => 'Entry',
        'description' => 'Sign-in (with the email code / two-factor steps when the account needs them), then onboarding and verification: the agent completes the profile, uploads the documents and submits it for approval. `GET /api/crm/auth/me` says what is still missing. Until the account is approved, the Leads and Listings endpoints answer `403`.',
        'screens' => [
            'Sign in' => [
                'POST /api/crm/auth/login',
                'POST /api/crm/auth/login/verify-email',
                'POST /api/crm/auth/login/resend-email',
                'POST /api/crm/auth/login/two-factor',
                'GET /api/crm/auth/me',
                'POST /api/crm/auth/logout',
            ],
            'Two-factor set-up' => [
                'GET /api/crm/account/two-factor',
                'POST /api/crm/account/two-factor/setup',
                'POST /api/crm/account/two-factor/confirm',
                'POST /api/crm/account/two-factor/skip',
            ],
            'Onboarding & verification' => [
                'GET /api/crm/profile',
                'POST /api/crm/profile',
                'POST /api/crm/profile/avatar',
                'DELETE /api/crm/profile/avatar',
                'GET /api/crm/profile/brokerages',
                'GET /api/crm/profile/documents/{field}',
                'POST /api/crm/profile/documents/{field}',
                'DELETE /api/crm/profile/documents/{field}',
                'POST /api/crm/profile/submit',
            ],
        ],
    ],
    [
        'group' => 'Home',
        'description' => 'New leads, leads awaiting a first response, listings needing action — from the dashboard. **Today\'s follow-ups** have no endpoint yet (see the introduction).',
        'screens' => [
            'Home' => [
                'GET /api/crm/dashboard',
            ],
        ],
        'also' => [
            'GET /api/crm/leads',
            'GET /api/crm/properties',
            'GET /api/crm/notifications',
        ],
    ],
    [
        'group' => 'Leads',
        'description' => 'The inbox (enquiries and viewings from the website, the app, Facebook and Property Finder) and one lead: contact details, stage, tags, notes and — for agency managers — assigning it to an agent.',
        'screens' => [
            'Inbox' => [
                'GET /api/crm/leads',
                'GET /api/crm/leads/meta',
                'GET /api/crm/leads/options/{type}',
            ],
            'Lead' => [
                'GET /api/crm/leads/{id}',
                'PATCH /api/crm/leads/{id}',
                'PUT /api/crm/leads/{id}/stage',
                'PUT /api/crm/leads/{id}/tags',
                'POST /api/crm/leads/{id}/notes',
                'POST /api/crm/leads/{id}/contacts',
                'PUT /api/crm/leads/{id}/contacts/{contactId}/primary',
                'POST /api/crm/leads/{id}/assign',
            ],
        ],
    ],
    [
        'group' => 'Add listing',
        'description' => 'Permit, location, details, media, preview. Drafts are kept on the phone until the agent submits, then sent in one `POST /api/crm/properties` (multipart, photos included).',
        'screens' => [
            'Add listing' => [
                'GET /api/crm/properties/create',
                'POST /api/crm/properties/permit/validate',
                'GET /api/crm/properties/nearby-places',
                'GET /api/crm/properties/agent-options',
                'POST /api/crm/properties',
            ],
        ],
    ],
    [
        'group' => 'Listings',
        'description' => 'My listings by state (active, inactive, permit review), quick sold / rented, edit, photos and the sold / rented proof documents.',
        'screens' => [
            'My listings' => [
                'GET /api/crm/properties',
                'GET /api/crm/properties/{id}',
                'POST /api/crm/properties/{id}/toggle-status',
            ],
            'Edit' => [
                'GET /api/crm/properties/{id}/edit',
                'PUT /api/crm/properties/{id}',
                'POST /api/crm/properties/{id}/images/reorder',
                'DELETE /api/crm/properties/{id}/images/{number}',
            ],
            'Sold / rented' => [
                'GET /api/crm/properties/{id}/sale-leads',
                'POST /api/crm/properties/{id}/mark-sold',
                'GET /api/crm/sold-listings',
                'GET /api/crm/sold-listings/{id}/documents/{kind}',
            ],
        ],
        'also' => [
            'POST /api/crm/properties/permit/validate',
            'GET /api/crm/properties/nearby-places',
            'GET /api/crm/properties/agent-options',
        ],
    ],
    [
        'group' => 'More',
        'description' => 'Team view for agency managers, notifications, profile and support.',
        'screens' => [
            'Team (agency managers)' => [
                'GET /api/crm/agents',
                'GET /api/crm/agents/{id}',
            ],
            'Notifications' => [
                'GET /api/crm/notifications',
                'POST /api/crm/notifications/read-all',
                'POST /api/crm/notifications/{id}/read',
            ],
            'Profile' => [
                'POST /api/crm/profile/email/request',
                'POST /api/crm/profile/email/verify',
            ],
            'Support' => [
                'GET /api/crm/support/meta',
                'GET /api/crm/support/tickets',
                'POST /api/crm/support/tickets',
                'GET /api/crm/support/tickets/{ticket}',
                'POST /api/crm/support/tickets/{ticket}/reply',
                'POST /api/crm/support/tickets/{ticket}/resolve',
                'POST /api/crm/support/tickets/{ticket}/reopen',
                'GET /api/crm/support/tickets/{ticket}/attachments/{message}',
            ],
        ],
        'also' => [
            'GET /api/crm/profile',
            'POST /api/crm/profile',
            'POST /api/crm/profile/avatar',
            'POST /api/crm/auth/logout',
        ],
    ],
];

$intro = <<<'INTRO'
    Only the CRM endpoints the **agent app's first release** calls, filed under the app's tabs:
    **Entry → Home → Leads → Add listing → Listings → More**. Each tab's introduction also lists the
    *connected* endpoints it calls that are documented under another tab. The complete CRM API is at
    [/docs/crm](/docs/crm).

    Review queues, verification, reported listings, push campaigns and app settings stay in the
    **web CMS** (Super Admin) and are not part of the app.

    ## Not in the API yet

    These parts of the app map have no endpoint yet. Agree on them with the backend team before building:

    | Screen | Missing |
    |---|---|
    | Entry | Joining by **invite**: an invited / newly added agent sets a password from the emailed web link; the app has no endpoint for it. Sign-in is email + password (with an email code), not **phone OTP**. |
    | Home / Leads | **Follow-ups** — a lead's *next follow-up* date and the *today's follow-ups* list. |
    | Leads | Inbox by channel (**calls / WhatsApp**) — a lead's source is its channel; there is no call or WhatsApp log. |
    | More | **Push notifications** (device-token registration) — the in-app list exists. |


    INTRO;

return array_replace($base, [
    'title' => 'MW Realty — Agent App API',
    'description' => 'The CRM endpoints the MW Realty agent app calls, by app tab: Entry, Home, Leads, Add listing, Listings, More.',
    'intro_text' => $intro . $base['intro_text'],

    'app_screens' => $screens,

    'routes' => [
        [
            'match' => ['prefixes' => [], 'domains' => ['*']],
            // Every endpoint in the map (inline: Scribe is a dev dependency, so no class calls at config load).
            'include' => array_values(array_unique(array_merge(...array_map(fn ($tab) => array_merge(...array_values($tab['screens'])), $screens)))),
            'exclude' => [],
        ],
    ],

    'static' => ['output_path' => 'public/docs/_scribe/app-agent'],

    'groups' => [
        'default' => 'Other',
        'order' => array_column($screens, 'group'),
    ],

    'strategies' => array_replace($base['strategies'], [
        'metadata' => [...Defaults::METADATA_STRATEGIES, AppScreens::class],
    ]),
]);
