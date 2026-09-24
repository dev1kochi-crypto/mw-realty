<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            // Identity (both agent & company), same as passport_no/passport_document.
            $table->date('passport_expiry')->nullable()->after('passport_document');

            // Company-only (the "tax expiry"), same as trn_number.
            $table->date('trn_expiry')->nullable()->after('trn_number');

            // Dedup tracking for the daily expiry-notification job: records the expiry date
            // that was last emailed for, so a user only ever gets one email per expiry event
            // (not one every day forever), but does get emailed again if they later update to
            // a new, later expiry date and that one also eventually passes.
            $table->date('passport_expiry_notified_at')->nullable()->after('passport_expiry');
            $table->date('trade_license_expiry_notified_at')->nullable()->after('trade_license_expiry');
            $table->date('trn_expiry_notified_at')->nullable()->after('trn_expiry');
        });
    }

    public function down(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropColumn([
                'passport_expiry',
                'trn_expiry',
                'passport_expiry_notified_at',
                'trade_license_expiry_notified_at',
                'trn_expiry_notified_at',
            ]);
        });
    }
};
