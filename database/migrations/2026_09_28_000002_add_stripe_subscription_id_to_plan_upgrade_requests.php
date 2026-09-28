<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // On-site checkout (Stripe Elements): the incomplete subscription the card payment confirms.
        Schema::table('plan_upgrade_requests', function (Blueprint $table) {
            $table->string('stripe_subscription_id')->nullable()->index()->after('stripe_checkout_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('plan_upgrade_requests', function (Blueprint $table) {
            $table->dropColumn('stripe_subscription_id');
        });
    }
};
