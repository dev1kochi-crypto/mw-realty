<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The brokerage an agent names in their RERA/KYC details — information only. It is NOT agency
 * membership (company_id): joining an agency, and moving onto its plan, only happens through an
 * accepted invitation / join request approved by admin (AgencyMembershipService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->string('affiliated_brokerage')->nullable()->after('brn_number');
        });
    }

    public function down(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropColumn('affiliated_brokerage');
        });
    }
};
