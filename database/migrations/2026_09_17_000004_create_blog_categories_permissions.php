<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up()
    {
        $permissions = collect(['view', 'create', 'edit', 'delete'])
            ->map(fn ($action) => Permission::firstOrCreate(['name' => "blog-categories.{$action}", 'guard_name' => 'cms']));

        $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'cms')->first();
        $superadmin?->givePermissionTo($permissions);
    }

    public function down()
    {
        Permission::where('guard_name', 'cms')
            ->where('name', 'like', 'blog-categories.%')
            ->delete();
    }
};
