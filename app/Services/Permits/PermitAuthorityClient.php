<?php

namespace App\Services\Permits;

/**
 * One permit authority's validation API (DLD Trakheesi, ADREC).
 *
 * check() returns PermitCheck::verified($data) with $data normalised to these keys (any may be
 * missing when the authority doesn't return it):
 *
 *   expires_at     Y-m-d          permit expiry
 *   category       residential | commercial
 *   listing_type   rent | sale
 *   property_type  a "property_type" option code (apartment, villa …)
 *   bedrooms       int            (0 = studio)
 *   sqft           int
 *   price          float
 *   zone_name      string         the permit's zone / community, shown on the website
 *   location       a "location" option code, when the zone matches one
 *   qr_url         string         QR image the authority issued, if it returns one
 *   verify_url     string         public page that confirms the permit
 *   raw            array          the authority's own response, kept for Super Admin
 */
interface PermitAuthorityClient
{
    /** Whether the API is switched on and has credentials (config/permits.php). */
    public function isConfigured(): bool;

    public function check(string $licenseNo, string $permitNo): PermitCheck;
}
