<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agency lead assignment options (My Agents): automatic round robin or manual assignment, and which
 * kinds of lead round robin covers. Existing agencies keep today's behaviour: automatic, every kind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_lead_assignment_settings', function (Blueprint $table) {
            $table->string('mode', 20)->default('automatic')->after('agency_id');
            // Lead kinds round robin assigns automatically (property / generic / facebook); null = all.
            $table->json('round_robin_sources')->nullable()->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('agency_lead_assignment_settings', function (Blueprint $table) {
            $table->dropColumn(['mode', 'round_robin_sources']);
        });
    }
};
