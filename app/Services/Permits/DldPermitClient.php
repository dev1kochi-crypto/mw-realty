<?php

namespace App\Services\Permits;

/**
 * DLD (Dubai Land Department) Trakheesi advertising-permit validation: the brokerage's ORN +
 * the RERA permit number → the permit's details.
 *
 * TO CONNECT: implement request() from DLD's API documentation (authentication, endpoint, request
 * fields, response fields → the keys on PermitAuthorityClient), set the DLD_PERMIT_API_* values in
 * .env and DLD_PERMIT_API_ENABLED=true. Nothing else in the app changes.
 */
class DldPermitClient extends HttpPermitClient
{
    protected function key(): string
    {
        return 'dld';
    }

    protected function authorityName(): string
    {
        return 'DLD';
    }

    protected function request(string $licenseNo, string $permitNo): PermitCheck
    {
        // Deliberately not guessed: the endpoint, auth flow and field names must come from DLD's
        // API documentation. Until then isConfigured() is false and this is never reached.
        throw new \RuntimeException('DLD permit API request mapping is not implemented yet (see class docblock).');
    }
}
