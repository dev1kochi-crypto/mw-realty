<?php

use Knuckles\Scribe\Config\AuthIn;
use Knuckles\Scribe\Config\Defaults;
use Knuckles\Scribe\Extracting\Strategies;

use function Knuckles\Scribe\Config\configureStrategy;
use function Knuckles\Scribe\Config\removeStrategies;

// Only the most common configs are shown. See the https://scribe.knuckles.wtf/laravel/reference/config for all.

return [
    // The HTML <title> for the generated documentation.
    'title' => 'MW Realty — Frontend API',

    // A short description of your API. Will be included in the docs webpage, Postman collection and OpenAPI spec.
    'description' => 'REST API for the MW Realty mobile app: property search, listings, agents, content, customer accounts, enquiries and the AI assistant.',

    // Text to place in the "Introduction" section, right after the `description`. Markdown and HTML are supported.
    'intro_text' => <<<'INTRO'
            The same API the website uses. All responses are JSON. Example responses for `GET` endpoints were recorded from real data, so field names and nesting are exact.

            ## Headers

            Send these on every request:

            | Header | Value | Why |
            |---|---|---|
            | `Accept` | `application/json` | Errors always come back as JSON. |
            | `X-Device-Id` | A random id (16–60 letters, digits or `-`, e.g. a UUID) the app creates on first launch and keeps | Identifies the device for the AI chat, enquiry history and listing stats, in place of the website's cookie. Without it the AI chat cannot continue a conversation and screen views are not recorded. |
            | `Authorization` | `Bearer {token}` | Only when a user is signed in. Required for the **User Account** endpoints; optional elsewhere (enquiries sent with it appear in the user's account). |
            | `User-Agent` | Your app's name/version | Requests without one are treated as bots and not tracked. |

            ## Signing in

            1. **Sign up:** `POST /api/auth/register` → emails a 4-digit code → `POST /api/auth/verify-otp` returns a `token`.
            2. **Sign in:** `POST /api/auth/login` (email + password) or `POST /api/auth/google` (access token from the native Google Sign-In SDK) returns a `token`.
            3. Store the token securely (Keychain / Keystore) and send it as `Authorization: Bearer {token}`. Tokens don't expire; `POST /api/auth/logout` revokes the current one.
            4. A `401` on an authenticated endpoint means the token is no longer valid: clear it and show the sign-in screen.

            ## Language and currency

            * `GET /api/languages` lists the languages (currently `en` and `ar`). Send `?lang={code}` on content endpoints; titles, descriptions and labels come back translated.
            * `GET /api/static-translations?lang={code}` returns the UI copy (button labels etc.).
            * Every price is in **AED**. `GET /api/currencies` returns exchange `rate`s (units per 1 AED) for display conversion.

            ## Response format

            Every response (success and error) has the same shape:

            ```json
            { "success": true, "message": "Leads retrieved successfully.", "data": { } }
            ```

            * `success` — `true` / `false`. `message` — a sentence you can show the user.
            * `data` — the payload: an object, a list, or `null`. **Paginated lists** are `data: { "items": [...], "meta": {...}, "links": {...} }`; request the next page with `?page=N`.
            * **Errors** keep the same keys with `success: false` and `data: null`. A `422` adds `errors`: `{"field": ["message"]}` — show them beside each field.

            | Status | Meaning |
            |---|---|
            | `401` | Missing, invalid or expired token — sign in again |
            | `403` | Not allowed (wrong role, plan, or account not approved) |
            | `404` | Not found |
            | `422` | Validation failed — see `errors` |
            | `429` | Too many requests — wait for the `Retry-After` header |

            ## Rate limits (per IP)

            Sign-in: 5/min per email, 30/min overall · Sign-up: 10/hour · OTP: 8/min · Forms and enquiries: 20/hour · AI chat: 8/min and 150/day · Location autocomplete and map: 120/min.

            ## Forms and reCAPTCHA

            The website protects forms (enquiries, viewings, downloads, contact, newsletter, careers, chat details) with Google reCAPTCHA v3 through the `recaptcha_token` field. **While reCAPTCHA is enabled on the server, these endpoints answer `422` with `errors.recaptcha_token` when the field is missing.** Sign-in, sign-up and the user account endpoints don't use it. How the app will pass this check is still open; agree on it with the backend team before building the forms.

            ## Images and files

            Image fields (`image`, `images`, `avatar_url`, `logo_url` …) are absolute URLs. A brochure or floor plan `download_url` is a signed link valid for 15 minutes and needs no token.
        INTRO,

    // The base URL displayed in the docs.
    // If you're using `laravel` type, you can set this to a dynamic string, like '{{ config("app.tenant_url") }}' to get a dynamic base URL.
    // Set SCRIBE_BASE_URL to the live API host before generating the copy you share.
    'base_url' => env('SCRIBE_BASE_URL', config('app.url')),

    // Routes to include in the docs
    'routes' => [
        [
            'match' => [
                // Match only routes whose paths match this pattern (use * as a wildcard to match any characters). Example: 'users/*'.
                'prefixes' => ['api/*'],

                // Match only routes whose domains match this pattern (use * as a wildcard to match any characters). Example: 'api.*'.
                'domains' => ['*'],
            ],

            // Include these routes even if they did not match the rules above.
            'include' => [
                // 'users.index', 'POST /new', '/auth/*'
            ],

            // Exclude these routes even if they matched the rules above.
            'exclude' => [
                // The agent/company CRM has its own docs (config/scribe_crm.php).
                'api/crm/*',
                // Server-to-server webhooks, not for the app.
                'POST /api/stripe/webhook', 'GET /api/webhooks/facebook', 'POST /api/webhooks/facebook',
            ],
        ],
    ],

    // The type of documentation output to generate.
    // - "static" will generate a static HTMl page in the /public/docs folder,
    // - "laravel" will generate the documentation as a Blade view, so you can add routing and authentication.
    // - "external_static" and "external_laravel" do the same as above, but pass the OpenAPI spec as a URL to an external UI template
    'type' => 'static',

    // See https://scribe.knuckles.wtf/laravel/reference/config#theme for supported options
    'theme' => 'default',

    'static' => [
        // HTML documentation, assets and Postman collection will be generated to this folder.
        // Source Markdown will still be in resources/docs.
        'output_path' => 'public/docs/_scribe/frontend',
    ],

    'laravel' => [
        // Whether to automatically create a docs route for you to view your generated docs. You can still set up routing manually.
        'add_routes' => true,

        // URL path to use for the docs endpoint (if `add_routes` is true).
        // By default, `/docs` opens the HTML page, `/docs.postman` opens the Postman collection, and `/docs.openapi` the OpenAPI spec.
        'docs_url' => '/docs',

        // Directory within `public` in which to store CSS and JS assets.
        // By default, assets are stored in `public/vendor/scribe`.
        // If set, assets will be stored in `public/{{assets_directory}}`
        'assets_directory' => null,

        // Middleware to attach to the docs endpoint (if `add_routes` is true).
        'middleware' => [],
    ],

    'external' => [
        'html_attributes' => [],
    ],

    'try_it_out' => [
        // Add a Try It Out button to your endpoints so consumers can test endpoints right from their browser.
        // Don't forget to enable CORS headers for your endpoints.
        'enabled' => true,

        // The base URL to use in the API tester. Leave as null to be the same as the displayed URL (`scribe.base_url`).
        'base_url' => null,

        // [Laravel Sanctum] Fetch a CSRF token before each request, and add it as an X-XSRF-TOKEN header.
        'use_csrf' => false,

        // The URL to fetch the CSRF token from (if `use_csrf` is true).
        'csrf_url' => '/sanctum/csrf-cookie',
    ],

    // How is your API authenticated? This information will be used in the displayed docs, generated examples and response calls.
    'auth' => [
        // Set this to true if ANY endpoints in your API use authentication.
        'enabled' => true,

        // Set this to true if your API should be authenticated by default. If so, you must also set `enabled` (above) to true.
        // You can then use @unauthenticated or @authenticated on individual endpoints to change their status from the default.
        'default' => false,

        // Where is the auth value meant to be sent in a request?
        'in' => AuthIn::BEARER->value,

        // The name of the auth parameter (e.g. token, key, apiKey) or header (e.g. Authorization, Api-Key).
        'name' => 'Authorization',

        // The value of the parameter to be used by Scribe to authenticate response calls.
        // This will NOT be included in the generated documentation. If empty, Scribe will use a random value.
        'use_value' => env('SCRIBE_AUTH_KEY'),

        // Placeholder your users will see for the auth parameter in the example requests.
        // Set this to null if you want Scribe to use a random value as placeholder instead.
        'placeholder' => '{YOUR_TOKEN}',

        // Any extra authentication-related info for your users. Markdown and HTML are supported.
        'extra_info' => 'Get a token from <code>POST /api/auth/login</code>, <code>/api/auth/verify-otp</code> or <code>/api/auth/google</code>, and send it as <code>Authorization: Bearer {token}</code>.',
    ],

    // Example requests for each endpoint will be shown in each of these languages.
    // Supported options are: bash, javascript, php, python
    // To add a language of your own, see https://scribe.knuckles.wtf/laravel/advanced/example-requests
    // Note: does not work for `external` docs types
    'example_languages' => [
        'bash',
        'javascript',
    ],

    // Generate a Postman collection (v2.1.0) in addition to HTML docs.
    // For 'static' docs, the collection will be generated to public/docs/collection.json.
    // For 'laravel' docs, it will be generated to storage/app/scribe/collection.json.
    // Setting `laravel.add_routes` to true (above) will also add a route for the collection.
    'postman' => [
        'enabled' => true,

        'overrides' => [
            // 'info.version' => '2.0.0',
        ],
    ],

    // Generate an OpenAPI spec in addition to docs webpage.
    // For 'static' docs, the collection will be generated to public/docs/openapi.yaml.
    // For 'laravel' docs, it will be generated to storage/app/scribe/openapi.yaml.
    // Setting `laravel.add_routes` to true (above) will also add a route for the spec.
    'openapi' => [
        'enabled' => true,

        // The OpenAPI spec version to generate. Supported versions: '3.0.3', '3.1.0'.
        // OpenAPI 3.1 is more compatible with JSON Schema and is becoming the dominant version.
        // See https://spec.openapis.org/oas/v3.1.0 for details on 3.1 changes.
        'version' => '3.0.3',

        'overrides' => [
            // 'info.version' => '2.0.0',
        ],

        // Additional generators to use when generating the OpenAPI spec.
        // Should extend `Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator`.
        'generators' => [],
    ],

    'groups' => [
        // Endpoints which don't have a @group will be placed in this default group.
        'default' => 'Endpoints',

        // By default, Scribe will sort groups alphabetically, and endpoints in the order their routes are defined.
        // You can override this by listing the groups, subgroups and endpoints here in the order you want them.
        // See https://scribe.knuckles.wtf/blog/laravel-v4#easier-sorting and https://scribe.knuckles.wtf/laravel/reference/config#order for details
        // Note: does not work for `external` docs types
        'order' => [
            'App Config',
            'Home',
            'Search & Filters',
            'Properties',
            'Agents & Agencies',
            'User Auth',
            'User Account',
            'Leads & Enquiries',
            'AI Chat',
            'Contact & Newsletter',
            'Content',
            'Careers',
            'Tracking',
        ],
    ],

    // Custom logo path. This will be used as the value of the src attribute for the <img> tag,
    // so make sure it points to an accessible URL or path. Set to false to not use a logo.
    // For example, if your logo is in public/img:
    // - 'logo' => '../img/logo.png' // for `static` type (output folder is public/docs)
    // - 'logo' => 'img/logo.png' // for `laravel` type
    'logo' => false,

    // Customize the "Last updated" value displayed in the docs by specifying tokens and formats.
    // Examples:
    // - {date:F j Y} => March 28, 2022
    // - {git:short} => Short hash of the last Git commit
    // Available tokens are `{date:<format>}` and `{git:<format>}`.
    // The format you pass to `date` will be passed to PHP's `date()` function.
    // The format you pass to `git` can be either "short" or "long".
    // Note: does not work for `external` docs types
    'last_updated' => 'Last updated: {date:F j, Y}',

    'examples' => [
        // Set this to any number to generate the same example values for parameters on each run,
        'faker_seed' => 1234,

        // With API resources and transformers, Scribe tries to generate example models to use in your API responses.
        // By default, Scribe will try the model's factory, and if that fails, try fetching the first from the database.
        // You can reorder or remove strategies here.
        'models_source' => ['factoryMake', 'databaseFirst'],
    ],

    // The strategies Scribe will use to extract information about your routes at each stage.
    // Use configureStrategy() to specify settings for a strategy in the list.
    // Use removeStrategies() to remove an included strategy.
    'strategies' => [
        'metadata' => [
            ...Defaults::METADATA_STRATEGIES,
        ],
        'headers' => [
            ...Defaults::HEADERS_STRATEGIES,
            Strategies\StaticData::withSettings(data: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Device-Id' => '{DEVICE_ID}',
            ]),
        ],
        'urlParameters' => [
            ...Defaults::URL_PARAMETERS_STRATEGIES,
        ],
        'queryParameters' => [
            ...Defaults::QUERY_PARAMETERS_STRATEGIES,
        ],
        'bodyParameters' => [
            ...Defaults::BODY_PARAMETERS_STRATEGIES,
        ],
        // Response calls (hitting every GET endpoint against the database) are what makes generating
        // slow, and unauthenticated they only record 401s. SCRIBE_RESPONSE_CALLS=true in .env turns them on.
        'responses' => env('SCRIBE_RESPONSE_CALLS', false)
            ? configureStrategy(
                Defaults::RESPONSES_STRATEGIES,
                Strategies\Responses\ResponseCalls::withSettings(
                    only: ['GET *'],
                    config: ['app.debug' => false]
                )
            )
            : removeStrategies(Defaults::RESPONSES_STRATEGIES, [Strategies\Responses\ResponseCalls::class]),
        'responseFields' => [
            ...Defaults::RESPONSE_FIELDS_STRATEGIES,
        ],
    ],

    // For response calls, API resource responses and transformer responses,
    // Scribe will try to start database transactions, so no changes are persisted to your database.
    // Tell Scribe which connections should be transacted here. If you only use one db connection, you can leave this as is.
    'database_connections_to_transact' => [config('database.default')],

    'fractal' => [
        // If you are using a custom serializer with league/fractal, you can specify it here.
        'serializer' => null,
    ],
];
