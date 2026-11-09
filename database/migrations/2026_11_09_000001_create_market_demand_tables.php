<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymised marketplace demand (App\Services\MarketDemand\DemandAggregator, `market-demand:aggregate`):
 * what visitors search for, save, view, favourite and enquire about, rolled up per day by area,
 * purpose, property type, bedrooms and price band. Only counts are kept — each is the number of
 * different browsers that did it that day — never who did it, so it can be kept long after the raw
 * visitor_events are pruned (visitors:prune drops anonymous ones after 30 days).
 *
 *   market_demand_daily     — one row per day per segment.
 *   market_demand_amenities — amenities asked for in searches / present on viewed listings, per area.
 *
 * A null dimension means "not specified": a search without a bedroom filter has bedrooms = null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_demand_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('city', 100)->nullable();
            $table->string('community', 150)->nullable();
            $table->string('purpose', 10)->nullable();          // sale | rent
            $table->string('property_type', 50)->nullable();
            $table->unsignedTinyInteger('bedrooms')->nullable(); // 0 = studio, config max = "N+"
            $table->unsignedTinyInteger('price_band')->nullable(); // index into config market_demand.price_bands.{purpose}
            $table->unsignedInteger('searches')->default(0);
            $table->unsignedInteger('saved_searches')->default(0);
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('favorites')->default(0);
            $table->unsignedInteger('enquiries')->default(0);

            $table->index('date');
            $table->index(['community', 'purpose', 'date']);
            $table->index(['city', 'purpose', 'date']);
        });

        Schema::create('market_demand_amenities', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('city', 100)->nullable();
            $table->string('community', 150)->nullable();
            $table->string('purpose', 10)->nullable();
            $table->string('amenity', 60);
            $table->unsignedInteger('searches')->default(0);
            $table->unsignedInteger('views')->default(0);

            $table->index('date');
            $table->index(['community', 'purpose', 'date']);
            $table->index(['city', 'purpose', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_demand_amenities');
        Schema::dropIfExists('market_demand_daily');
    }
};
