<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_payments', function (Blueprint $table) {
            // paid | failed. A failed Stripe renewal is kept as a row (Stripe retries it); when a
            // retry succeeds the same row (same stripe_invoice_id) flips to paid.
            $table->string('status', 20)->default('paid')->after('amount')->index();
            $table->string('failure_reason', 500)->nullable()->after('status');
            $table->unsignedSmallInteger('attempts')->default(1)->after('failure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('plan_payments', fn (Blueprint $table) => $table->dropColumn(['status', 'failure_reason', 'attempts']));
    }
};
