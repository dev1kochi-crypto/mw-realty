<?php

use Database\Seeders\HelpGuideSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Help Desk — the "!" quick-guide popups shown on each CRM / portal screen, editable from
 * Admin › Help Desk. `topic` is the key a screen asks for (CRM route meta `help`); the starting
 * content is in HelpGuideSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_guides', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 60)->unique();
            $table->string('title');
            $table->string('icon', 60)->nullable();
            $table->text('intro')->nullable();
            $table->json('features')->nullable(); // [{icon, title, text}]
            $table->json('steps')->nullable();    // [{title, text}]
            $table->json('tips')->nullable();     // [string]
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // Without guides every screen's "!" would be empty, so the starting content goes in now.
        (new HelpGuideSeeder)->run();

        $permissions = collect(['view', 'create', 'edit', 'delete'])
            ->map(fn ($action) => Permission::firstOrCreate(['name' => "help-desk.{$action}", 'guard_name' => 'cms']));
        Role::where('name', 'superadmin')->where('guard_name', 'cms')->first()?->givePermissionTo($permissions);
    }

    public function down(): void
    {
        Schema::dropIfExists('help_guides');
        Permission::where('guard_name', 'cms')->where('name', 'like', 'help-desk.%')->delete();
    }
};
