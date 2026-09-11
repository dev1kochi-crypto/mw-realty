<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields the public Agent/Agency listing + detail pages will need once built (not yet in this
 * codebase) — captured now on the admin/portal forms so the data exists ahead of time. `bio` lives
 * inside `translations` (same JSON-per-locale pattern as the Plan model) since it's shown on a
 * language-switchable public page; `badges` is admin-only recognition, not client-editable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('office_address');
            $table->unsignedTinyInteger('years_of_experience')->nullable()->after('translations');
            $table->json('preferred_areas')->nullable()->after('years_of_experience');
            $table->string('website')->nullable()->after('preferred_areas');
            $table->unsignedSmallInteger('founding_year')->nullable()->after('website');
            $table->json('badges')->nullable()->after('founding_year');
        });
    }

    public function down(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropColumn(['translations', 'years_of_experience', 'preferred_areas', 'website', 'founding_year', 'badges']);
        });
    }
};
