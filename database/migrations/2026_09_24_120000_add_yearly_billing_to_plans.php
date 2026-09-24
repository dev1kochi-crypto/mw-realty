<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // `price` stays the monthly price; yearly_price NULL = the plan has no yearly option.
            $table->decimal('yearly_price', 10, 2)->nullable()->after('price');
            $table->string('stripe_yearly_price_id')->nullable()->after('stripe_price_amount');
            $table->unsignedBigInteger('stripe_yearly_price_amount')->nullable()->after('stripe_yearly_price_id');
        });

        Schema::table('portal_users', function (Blueprint $table) {
            $table->string('billing_interval', 10)->nullable()->after('plan_id'); // monthly | yearly
            $table->string('scheduled_interval', 10)->nullable()->after('scheduled_plan_id');
        });

        Schema::table('plan_upgrade_requests', function (Blueprint $table) {
            $table->string('billing_interval', 10)->default('monthly')->after('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('plan_upgrade_requests', fn (Blueprint $table) => $table->dropColumn('billing_interval'));
        Schema::table('portal_users', fn (Blueprint $table) => $table->dropColumn(['billing_interval', 'scheduled_interval']));
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn(['yearly_price', 'stripe_yearly_price_id', 'stripe_yearly_price_amount']));
    }
};
