# Introduction

The CRM endpoints the MW Realty agent app calls, by app tab: Entry, Home, Leads, Add listing, Listings, More.

<aside>
    <strong>Base URL</strong>: <code>http://127.0.0.1:8000</code>
</aside>

Only the CRM endpoints the **agent app's first release** calls, filed under the app's tabs:
**Entry → Home → Leads → Add listing → Listings → More**, plus **Featured & ads** (featured
listings and ads — reached from Listings and More). Each tab's introduction also lists the
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
| Featured & ads | **Buying credits** in the app — top-up is a Stripe card payment on the web; app stores may require in-app purchase for it. |

    The agent / company CRM API: leads, properties, agents, masters, reports, integrations and account settings. It powers both the CRM web app and the mobile app.

    ## Headers

    | Header | Value |
    |---|---|
    | `Accept` | `application/json` |
    | `Authorization` | `Bearer {token}` on every request except sign-in |

    ## Signing in

    1. `POST /api/crm/auth/login` with email + password returns a `token` (plus the two-factor / new-device steps when the account requires them).
    2. Send it as `Authorization: Bearer {token}`. The CRM web app uses the browser session instead.
    3. A `401` means the token is no longer valid: clear it and show the sign-in screen.

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

