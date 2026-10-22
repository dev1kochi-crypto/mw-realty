<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            // Set on self-registration: the portal keeps the user on the 2FA set-up step until they
            // set it up or choose "Skip for now" (see EnsurePortalTwoFactor).
            $table->boolean('two_factor_prompt_pending')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropColumn('two_factor_prompt_pending');
        });
    }
};
