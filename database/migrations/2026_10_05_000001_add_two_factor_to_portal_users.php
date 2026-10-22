<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            // Authenticator-app (TOTP — Authy, Google Authenticator, ...) 2FA. Secret and recovery
            // codes are stored encrypted (see PortalUser casts); confirmed_at marks it as enabled.
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            // Last accepted 30-second time step, so the same code can't be replayed.
            $table->unsignedBigInteger('two_factor_last_step')->nullable();
            // Company accounts only: makes 2FA mandatory for the company and all its agents.
            $table->boolean('two_factor_enforced')->default(false);
        });

        // Browsers that have passed the emailed "new device" check at login.
        Schema::create('portal_trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('user_agent', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_trusted_devices');

        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
                'two_factor_last_step', 'two_factor_enforced',
            ]);
        });
    }
};
