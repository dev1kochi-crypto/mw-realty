<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a connected agent moves onto the agency's plan, the plan they dropped (no refund) is kept on
 * the membership so both sides can see "switched from Basic" (AgencyMembershipService::moveOntoAgencyPlan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_agents', function (Blueprint $table) {
            $table->foreignId('previous_plan_id')->nullable()->after('rejection_reason')->constrained('plans')->nullOnDelete();
            $table->timestamp('plan_switched_at')->nullable()->after('previous_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('agency_agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_plan_id');
            $table->dropColumn('plan_switched_at');
        });
    }
};
