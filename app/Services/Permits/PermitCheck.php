<?php

namespace App\Services\Permits;

/**
 * Result of validating a permit with DLD / ADREC.
 *
 *   verified    — the authority confirmed it; $data holds the permit's details (see PermitAuthorityClient)
 *   invalid     — the authority says no (unknown / expired / issued to another license …); $message says why
 *   unavailable — couldn't ask (API not connected, timeout, outage); the listing can still be saved and
 *                 Super Admin checks the permit by hand before approving
 */
final class PermitCheck
{
    public const VERIFIED = 'verified';
    public const INVALID = 'invalid';
    public const UNAVAILABLE = 'unavailable';

    private function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly array $data = [],
    ) {
    }

    public static function verified(array $data, string $message = 'Permit verified successfully.'): self
    {
        return new self(self::VERIFIED, $message, $data);
    }

    public static function invalid(string $message): self
    {
        return new self(self::INVALID, $message);
    }

    public static function unavailable(string $message): self
    {
        return new self(self::UNAVAILABLE, $message);
    }

    public function isVerified(): bool
    {
        return $this->status === self::VERIFIED;
    }
}
