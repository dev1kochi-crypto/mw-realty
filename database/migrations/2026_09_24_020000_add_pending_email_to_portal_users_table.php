<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Staged new-email address while an OTP change is in progress (see
 *  PortalProfileController::requestEmailChange/verifyEmailChange) — the real `email` column
 *  only gets overwritten once the code sent to the new address is verified. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->string('pending_email')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropColumn('pending_email');
        });
    }
};
