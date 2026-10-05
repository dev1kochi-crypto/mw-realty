<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permit flow per emirate (App\Support\PermitRules): which permit a listing needs (RERA / DTCM /
 * ADREC / none), the license it was issued under, and the result of validating it with the
 * authority (App\Services\Permits\PermitVerifier).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // rera | dtcm | none (DIFC/JAFZA) | adrec | not_required — see PermitRules::TYPES.
            $table->string('permit_type', 20)->nullable()->after('emirate');
            // Northern Emirates only: al_ain (ADREC permit) or other (no permit).
            $table->string('permit_city', 30)->nullable()->after('permit_type');
            // License the permit was issued under (agency ORN / ADREC brokerage no.), copied at validation.
            $table->string('permit_license_no', 64)->nullable()->after('permit_number');
            // Set when DLD / ADREC confirmed the permit (or Super Admin checked it by hand on approval).
            $table->timestamp('permit_verified_at')->nullable()->after('permit_license_no');
            $table->string('permit_verified_via', 20)->nullable()->after('permit_verified_at'); // dld | adrec | manual
            // What the authority returned for the permit (category, purpose, type, zone, size …).
            $table->json('permit_data')->nullable()->after('permit_verified_via');
        });

        Schema::table('portal_users', function (Blueprint $table) {
            // Abu Dhabi (ADREC) brokerage registration number — the RERA ORN only covers Dubai.
            $table->string('adrec_license_no', 50)->nullable()->after('orn_number');
        });
    }

    public function down(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropColumn('adrec_license_no');
        });
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['permit_type', 'permit_city', 'permit_license_no', 'permit_verified_at', 'permit_verified_via', 'permit_data']);
        });
    }
};
