<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for a website lead's Insights panel (App\Services\Visitors\VisitorInsights) when one lead
 * has a very long history:
 *   (visitor_lead_id, id)                                  — the Activity Timeline, keyset-paged newest first.
 *   (visitor_lead_id, type, property_id, duration_seconds) — the per-type counts / time totals and
 *                                                            Most Interested Properties, read from the index alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_events', function (Blueprint $table) {
            $table->index(['visitor_lead_id', 'id'], 'visitor_events_lead_id_index');
            $table->index(['visitor_lead_id', 'type', 'property_id', 'duration_seconds'], 'visitor_events_lead_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_events', function (Blueprint $table) {
            $table->dropIndex('visitor_events_lead_id_index');
            $table->dropIndex('visitor_events_lead_type_index');
        });
    }
};
