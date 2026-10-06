<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listing performance counters, one row per listing per day (see PropertyDailyStat::bump):
 *   impressions — the listing's card was shown on screen (search results, home, profiles, AI chat…)
 *   clicks      — its detail page was opened
 *   lead_clicks — a visitor clicked to contact (call / WhatsApp / email / enquiry / viewing / brochure)
 * Aggregated rather than per event, so it stays small however many times cards are seen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('lead_clicks')->default(0);
            $table->timestamps();

            $table->unique(['property_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_daily_stats');
    }
};
