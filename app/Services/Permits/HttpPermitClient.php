<?php

namespace App\Services\Permits;

use Illuminate\Support\Facades\Log;

/**
 * Shared plumbing for the authority clients: configuration, and turning failures into
 * PermitCheck::unavailable() so a slow or down API never blocks saving a listing.
 *
 * Each authority implements request(): the actual HTTP call and the mapping of its response to the
 * keys documented on PermitAuthorityClient. That mapping is written from the authority's API
 * documentation — until it is, `enabled` stays false in config/permits.php and Validate reports the
 * service as not connected.
 */
abstract class HttpPermitClient implements PermitAuthorityClient
{
    /** config/permits.php section: "dld" / "adrec". */
    abstract protected function key(): string;

    /** Name shown in messages ("DLD", "ADREC"). */
    abstract protected function authorityName(): string;

    /** Calls the API and maps its answer. Throw on transport errors; return invalid() for a "no". */
    abstract protected function request(string $licenseNo, string $permitNo): PermitCheck;

    protected function config(string $item, mixed $default = null): mixed
    {
        return config("permits.{$this->key()}.{$item}", $default);
    }

    public function isConfigured(): bool
    {
        return (bool) $this->config('enabled')
            && filled($this->config('base_url'))
            && filled($this->config('client_id'))
            && filled($this->config('client_secret'));
    }

    public function check(string $licenseNo, string $permitNo): PermitCheck
    {
        if (!$this->isConfigured()) {
            return PermitCheck::unavailable("Automatic {$this->authorityName()} validation isn't connected yet — save the listing and Super Admin will check the permit before approving it.");
        }

        try {
            return $this->request($licenseNo, $permitNo);
        } catch (\Throwable $e) {
            Log::warning("{$this->authorityName()} permit validation failed: " . $e->getMessage(), ['permit' => $permitNo]);

            return PermitCheck::unavailable("{$this->authorityName()} couldn't be reached just now. Try Validate again in a moment, or save the listing and Super Admin will check the permit before approving it.");
        }
    }
}
