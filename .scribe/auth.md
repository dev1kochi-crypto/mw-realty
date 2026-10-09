# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Get a token from <code>POST /api/auth/login</code>, <code>/api/auth/verify-otp</code> or <code>/api/auth/google</code>, and send it as <code>Authorization: Bearer {token}</code>.
