<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->index()->after('last_payment_at');
            $table->string('stripe_subscription_id')->nullable()->index()->after('stripe_customer_id');
            // Mirrors Stripe's subscription status: active, past_due, canceled, incomplete, ...
            $table->string('subscription_status', 30)->nullable()->after('stripe_subscription_id');
            $table->dateTime('subscription_renews_at')->nullable()->after('subscription_status');
            $table->boolean('subscription_cancel_at_period_end')->default(false)->after('subscription_renews_at');
        });

        // Stripe Product/Price created lazily per plan; a new Price is made whenever the plan's
        // price changes (Stripe prices are immutable), tracked via stripe_price_amount.
        Schema::table('plans', function (Blueprint $table) {
            $table->string('stripe_product_id')->nullable()->after('reports_access');
            $table->string('stripe_price_id')->nullable()->after('stripe_product_id');
            $table->unsignedBigInteger('stripe_price_amount')->nullable()->after('stripe_price_id');
        });

        // Stripe coupons are immutable too — re-created when the discount definition changes.
        Schema::table('coupons', function (Blueprint $table) {
            $table->string('stripe_coupon_id')->nullable()->after('status');
            $table->string('stripe_coupon_signature', 64)->nullable()->after('stripe_coupon_id');
        });

        Schema::table('plan_payments', function (Blueprint $table) {
            $table->string('stripe_invoice_id')->nullable()->unique()->after('coupon_code');
            $table->string('stripe_hosted_invoice_url', 500)->nullable()->after('stripe_invoice_id');
        });

        Schema::table('plan_upgrade_requests', function (Blueprint $table) {
            // Online upgrades are tracked here too, with status 'checkout' until Stripe confirms.
            $table->string('stripe_checkout_session_id')->nullable()->unique()->after('final_price');
        });
    }

    public function down(): void
    {
        Schema::table('plan_upgrade_requests', fn (Blueprint $table) => $table->dropColumn('stripe_checkout_session_id'));
        Schema::table('plan_payments', fn (Blueprint $table) => $table->dropColumn(['stripe_invoice_id', 'stripe_hosted_invoice_url']));
        Schema::table('coupons', fn (Blueprint $table) => $table->dropColumn(['stripe_coupon_id', 'stripe_coupon_signature']));
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn(['stripe_product_id', 'stripe_price_id', 'stripe_price_amount']));
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropColumn(['stripe_customer_id', 'stripe_subscription_id', 'subscription_status', 'subscription_renews_at', 'subscription_cancel_at_period_end']);
        });
    }
};
