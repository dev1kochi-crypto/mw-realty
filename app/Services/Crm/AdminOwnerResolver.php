<?php

namespace App\Services\Crm;

use App\Models\PortalUser;
use Illuminate\Support\Str;

/**
 * Resolves the single PortalUser row used to attribute CRM master data
 * (Stages/Tags/Sources, and any admin-managed leads) to "Admin", reusing the
 * existing portal_user_id ownership column/relation instead of adding a
 * parallel owner column for the CMS superadmin.
 */
class AdminOwnerResolver
{
    public const EMAIL = 'admin@internal.mw-realty';
    public const NAME = 'Admin';

    public static function resolve(): PortalUser
    {
        return PortalUser::firstOrCreate(
            ['email' => self::EMAIL],
            [
                'type' => 'company',
                'name' => self::NAME,
                'company_name' => self::NAME,
                'password' => bcrypt(Str::random(40)),
                'status' => 'approved',
                'is_active' => true,
                'status_changed_at' => now(),
            ]
        );
    }
}
