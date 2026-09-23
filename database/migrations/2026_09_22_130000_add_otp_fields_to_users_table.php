<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Hashed like a password (not stored plain) — a DB leak shouldn't hand out live
            // codes. email_verified_at (already on this table, currently unused) is reused as
            // the "verified" flag, set the moment the code is confirmed.
            $table->string('otp_code')->nullable()->after('password');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['otp_code', 'otp_expires_at']);
        });
    }
};
