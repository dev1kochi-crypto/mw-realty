<?php

namespace App\Services\Agency;

use App\Models\PortalUser;
use Illuminate\Support\Facades\Auth;

/** Who made an assignment/ownership change — written to the history tables. */
final class AssignmentActor
{
    public const SYSTEM = 'system';
    public const ADMIN = 'admin';
    public const AGENCY = 'agency';
    public const AGENT = 'agent';

    public function __construct(public readonly string $type, public readonly ?int $id = null)
    {
    }

    public static function system(): self
    {
        return new self(self::SYSTEM);
    }

    public static function admin(?int $id): self
    {
        return new self(self::ADMIN, $id);
    }

    public static function portal(PortalUser $user): self
    {
        return new self($user->isAgency() ? self::AGENCY : self::AGENT, $user->id);
    }

    /** The signed-in portal account, else the signed-in CMS admin (same precedence as OwnerContext). */
    public static function current(): self
    {
        if ($user = Auth::guard('portal')->user()) {
            return self::portal($user);
        }

        return self::admin(Auth::guard('cms')->id());
    }
}
