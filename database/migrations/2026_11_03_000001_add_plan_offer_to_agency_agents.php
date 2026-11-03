<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An agent the Super Admin linked to an agency keeps their own plan. The agency can offer its plan
 * from Agents; the agent accepts or declines on My Agency (AgencyMembershipService::offerAgencyPlan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_agents', function (Blueprint $table) {
            $table->string('plan_offer_status', 20)->nullable()->after('plan_switched_at'); // offered | accepted | declined
            $table->timestamp('plan_offered_at')->nullable()->after('plan_offer_status');
            $table->timestamp('plan_offer_responded_at')->nullable()->after('plan_offered_at');
        });
    }

    public function down(): void
    {
        Schema::table('agency_agents', function (Blueprint $table) {
            $table->dropColumn(['plan_offer_status', 'plan_offered_at', 'plan_offer_responded_at']);
        });
    }
};
