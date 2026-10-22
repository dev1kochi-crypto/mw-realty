<?php

namespace App\Services\Permits;

/**
 * ADREC (Abu Dhabi Real Estate Centre) advertising-permit validation: the broker license
 * (brokerage registration number) + the ADREC permit number → the permit's details. Also used for
 * Al Ain listings.
 *
 * TO CONNECT: implement request() from ADREC's API documentation, set the ADREC_PERMIT_API_* values
 * in .env and ADREC_PERMIT_API_ENABLED=true. Nothing else in the app changes.
 */
class AdrecPermitClient extends HttpPermitClient
{
    protected function key(): string
    {
        return 'adrec';
    }

    protected function authorityName(): string
    {
        return 'ADREC';
    }

    protected function request(string $licenseNo, string $permitNo): PermitCheck
    {
        // Deliberately not guessed: the endpoint, auth flow and field names must come from ADREC's
        // API documentation. Until then isConfigured() is false and this is never reached.
        throw new \RuntimeException('ADREC permit API request mapping is not implemented yet (see class docblock).');
    }
}
