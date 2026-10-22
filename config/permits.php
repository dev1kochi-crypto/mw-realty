<?php

/*
 * Property advertising permits (App\Support\PermitRules, App\Services\Permits\PermitVerifier).
 *
 * "house": MW Realty's own licenses, used for listings Super Admin creates directly (no portal
 * owner). Agency / agent listings use the owner's ORN (portal_users.orn_number) or ADREC brokerage
 * number (portal_users.adrec_license_no).
 *
 * "dld" / "adrec": the authorities' permit-validation APIs. Leave `enabled` false until the client
 * for that API is finished (App\Services\Permits\DldPermitClient / AdrecPermitClient) — until then
 * Validate reports the service as not connected and Super Admin checks the permit on approval.
 */
return [
    'house' => [
        'name' => env('HOUSE_LICENSE_NAME', 'Mighty Warners Real Estate L.L.C'),
        'orn' => env('HOUSE_ORN'),
        'adrec' => env('HOUSE_ADREC_LICENSE'),
    ],

    'dld' => [
        'enabled' => (bool) env('DLD_PERMIT_API_ENABLED', false),
        'base_url' => env('DLD_PERMIT_API_URL'),
        'client_id' => env('DLD_PERMIT_API_CLIENT_ID'),
        'client_secret' => env('DLD_PERMIT_API_CLIENT_SECRET'),
        'timeout' => (int) env('DLD_PERMIT_API_TIMEOUT', 15),
    ],

    'adrec' => [
        'enabled' => (bool) env('ADREC_PERMIT_API_ENABLED', false),
        'base_url' => env('ADREC_PERMIT_API_URL'),
        'client_id' => env('ADREC_PERMIT_API_CLIENT_ID'),
        'client_secret' => env('ADREC_PERMIT_API_CLIENT_SECRET'),
        'timeout' => (int) env('ADREC_PERMIT_API_TIMEOUT', 15),
    ],

    // true: every listing waits for Super Admin to approve it on Listing Permits before it goes live
    // (Validate still checks the permit). false: a permit verified with DLD / ADREC goes live by itself.
    // DTCM and "None" (DIFC / JAFZA) can't be validated online, so they always need approval
    // (PermitRules::needsApproval).
    'superadmin_approval' => (bool) env('LISTING_SUPERADMIN_APPROVAL', false),

    // How long a successful Validate stays usable for saving the listing (minutes).
    'verification_ttl' => 120,
];
