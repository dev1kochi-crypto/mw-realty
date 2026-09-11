<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropColumn('license_no');

            // Identity (both agent & company)
            $table->string('nationality')->nullable()->after('phone');
            $table->string('avatar')->nullable()->after('nationality');
            $table->string('emirates_id_no')->nullable()->after('avatar');
            $table->string('emirates_id_document')->nullable()->after('emirates_id_no');
            $table->string('passport_no')->nullable()->after('emirates_id_document');
            $table->string('passport_document')->nullable()->after('passport_no');

            // Agent (RERA broker) — an individual BRN holder must be affiliated with a registered brokerage
            $table->string('brn_number')->nullable()->after('passport_document');
            $table->string('rera_card_document')->nullable()->after('brn_number');
            $table->string('affiliated_company')->nullable()->after('rera_card_document');

            // Company (RERA/DLD brokerage licensing)
            $table->string('trade_license_no')->nullable()->after('affiliated_company');
            $table->string('trade_license_document')->nullable()->after('trade_license_no');
            $table->date('trade_license_expiry')->nullable()->after('trade_license_document');
            $table->string('orn_number')->nullable()->after('trade_license_expiry');
            $table->string('rera_certificate_document')->nullable()->after('orn_number');
            $table->string('office_address')->nullable()->after('rera_certificate_document');
            $table->string('trn_number')->nullable()->after('office_address');
            $table->string('authorized_signatory_name')->nullable()->after('trn_number');
            $table->string('landline')->nullable()->after('authorized_signatory_name');

            // Moderation
            $table->text('rejection_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropColumn([
                'nationality', 'avatar', 'emirates_id_no', 'emirates_id_document',
                'passport_no', 'passport_document', 'brn_number', 'rera_card_document',
                'affiliated_company', 'trade_license_no', 'trade_license_document',
                'trade_license_expiry', 'orn_number', 'rera_certificate_document',
                'office_address', 'trn_number', 'authorized_signatory_name', 'landline',
                'rejection_reason',
            ]);
            $table->string('license_no')->nullable()->after('phone');
        });
    }
};
