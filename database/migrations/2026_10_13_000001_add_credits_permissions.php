<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Admin › Credits and Admin › Credit History get their own permissions (they were under plans.*).
 * New installs get them from CmsRolesPermissionsSeeder (config cms-kit.common.modules); this adds them
 * to existing installs, granted to superadmin and to any role that could already manage plans.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['credits.view', 'credits.edit', 'credit-history.view', 'credit-history.edit'];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'cms']);
        }

        Role::where('guard_name', 'cms')->get()->each(function (Role $role) {
            if ($role->name === 'superadmin') {
                $role->givePermissionTo(self::PERMISSIONS);
                return;
            }
            if ($role->hasPermissionTo('plans.view', 'cms')) {
                $role->givePermissionTo(['credits.view', 'credit-history.view']);
            }
            if ($role->hasPermissionTo('plans.edit', 'cms')) {
                $role->givePermissionTo(['credits.edit', 'credit-history.edit']);
            }
        });

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'cms')->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
