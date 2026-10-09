# Introduction

REST API for the MW Realty mobile app: property search, listings, agents, content, customer accounts, enquiries and the AI assistant.

<aside>
    <strong>Base URL</strong>: <code>http://localhost</code>
</aside>

    The same API the website uses. All responses are JSON. Example responses for `GET` endpoints were recorded from real data, so field names and nesting are exact.

    ## Headers

    Send these on every request:

    | Header | Value | Why |
    |---|---|---|
    | `Accept` | `application/json` | Errors always come back as JSON. |
    | `X-Device-Id` | A random id (16–60 letters, digits or `-`, e.g. a UUID) the app creates on first launch and keeps | Identifies the device for the AI chat, enquiry history and listing stats, in place of the website's cookie. Without it the AI chat cannot continue a conversation and screen views are not recorded. |
    | `Authorization` | `Bearer {token}` | Only when a customer is signed in. Required for the **Customer Account** endpoints; optional elsewhere (enquiries sent with it appear in the customer's account). |
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

    ## Pagination

    Paginated lists return a `pagination` object: `current_page`, `last_page` and usually `total`. Request the next page with `?page=N` until `current_page == last_page`.

    ## Errors

    | Status | Meaning | Body |
    |---|---|---|
    | `401` | Missing or invalid token | `{"message": "Unauthenticated."}` |
    | `404` | Not found (e.g. unpublished listing) | `{"message": "Not found"}` |
    | `422` | Validation failed | `{"message": "…", "errors": {"field": ["…"]}}`: show `errors` next to each field |
    | `429` | Too many requests | Wait for the `Retry-After` header (seconds) |

    ## Rate limits (per IP)

    Sign-in: 5/min per email, 30/min overall · Sign-up: 10/hour · OTP: 8/min · Forms and enquiries: 20/hour · AI chat: 8/min and 150/day · Location autocomplete and map: 120/min.

    ## Forms and reCAPTCHA

    The website protects forms (enquiries, viewings, downloads, contact, newsletter, careers, chat details) with Google reCAPTCHA v3 through the `recaptcha_token` field. **While reCAPTCHA is enabled on the server, these endpoints answer `422` with `errors.recaptcha_token` when the field is missing.** Sign-in, sign-up and the customer account endpoints don't use it. How the app will pass this check is still open; agree on it with the backend team before building the forms.

    ## Images and files

    Image fields (`image`, `images`, `avatar_url`, `logo_url` …) are absolute URLs. A brochure or floor plan `download_url` is a signed link valid for 15 minutes and needs no token.

