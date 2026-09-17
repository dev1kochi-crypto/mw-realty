<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up()
    {
        $view = Permission::firstOrCreate(['name' => 'section-headings.view', 'guard_name' => 'cms']);
        $edit = Permission::firstOrCreate(['name' => 'section-headings.edit', 'guard_name' => 'cms']);

        $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'cms')->first();
        $superadmin?->givePermissionTo([$view, $edit]);
    }

    public function down()
    {
        Permission::where('guard_name', 'cms')
            ->whereIn('name', ['section-headings.view', 'section-headings.edit'])
            ->delete();
    }
};
