<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Property Details › Market & Investment (App\Services\PropertyInsightService):
 *   property_insight_snapshots — the estimated market value / annual rent shown for a listing, one row
 *     per listing per day, so once it is sold or rented the estimate can be checked against the price paid.
 *   property_daily_stats.insight_views — the panel was looked at (report usage).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_insight_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->date('date');
            $table->string('purpose', 10); // sale | rent
            $table->decimal('asking_amount', 15, 2)->nullable();          // the listing's own price (annual for rent)
            $table->decimal('estimated_value', 15, 2)->nullable();        // sale only
            $table->string('value_source', 10)->nullable();               // verified | asking
            $table->decimal('estimated_annual_rent', 15, 2)->nullable();
            $table->string('rent_source', 10)->nullable();
            $table->unsignedInteger('sample_size')->default(0);
            $table->timestamps();

            $table->unique(['property_id', 'date']);
        });

        Schema::table('property_daily_stats', function (Blueprint $table) {
            $table->unsignedInteger('insight_views')->default(0)->after('lead_clicks');
        });
    }

    public function down(): void
    {
        Schema::table('property_daily_stats', function (Blueprint $table) {
            $table->dropColumn('insight_views');
        });
        Schema::dropIfExists('property_insight_snapshots');
    }
};
