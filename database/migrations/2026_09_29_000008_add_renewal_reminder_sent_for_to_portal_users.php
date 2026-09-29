<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            // The subscription_renews_at value the last "renews tomorrow" reminder was sent for, so
            // each billing period is reminded exactly once; the next period's new date re-arms it.
            $table->dateTime('renewal_reminder_sent_for')->nullable()->after('subscription_cancel_at_period_end');
        });
    }

    public function down(): void
    {
        Schema::table('portal_users', fn (Blueprint $table) => $table->dropColumn('renewal_reminder_sent_for'));
    }
};
