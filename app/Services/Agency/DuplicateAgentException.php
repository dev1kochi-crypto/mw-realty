<?php

namespace App\Services\Agency;

use App\Models\PortalUser;
use RuntimeException;

/** An agency tried to create an agent whose email/mobile already belongs to an account — invite them instead. */
class DuplicateAgentException extends RuntimeException
{
    public function __construct(public readonly PortalUser $existing)
    {
        parent::__construct('An account with this email or mobile already exists.');
    }
}
