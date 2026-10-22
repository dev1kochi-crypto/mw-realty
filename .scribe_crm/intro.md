# Introduction

REST API for the agent / company CRM (web app and mobile app).

<aside>
    <strong>Base URL</strong>: <code>http://127.0.0.1:8000</code>
</aside>

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

