<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Which CRM menu owns the listing: Properties ('residential') or Commercial ('commercial').
            // Same table, same form — the website's /properties and /commercial pages split on this.
            $table->string('segment', 20)->default('residential')->after('category')->index();
            // Start of a feature. A future value with featured = false means "scheduled"; the
            // properties:expire-featured command switches it live once this time is reached.
            $table->timestamp('featured_from')->nullable()->after('featured');
        });

        // Until now /commercial picked listings by these property types (the old
        // CommercialPageService::COMMERCIAL_TYPES), so they carry over as commercial.
        DB::table('properties')
            ->whereIn('property_type', ['office', 'retail-shop', 'warehouse', 'showroom'])
            ->update(['segment' => 'commercial']);

        DB::table('properties')->where('featured', true)->whereNull('featured_from')->update(['featured_from' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['segment']);
            $table->dropColumn(['segment', 'featured_from']);
        });
    }
};
