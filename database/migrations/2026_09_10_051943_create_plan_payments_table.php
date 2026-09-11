<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();

            // Snapshots — so history stays accurate even if the plan's name/price/cycle changes later
            $table->string('plan_name');
            $table->decimal('amount', 10, 2);
            $table->string('billing_cycle');

            // One record per billing period per subscriber. period_month is null for
            // yearly/one_time plans (the period is the whole year / a single payment).
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month')->nullable();
            $table->date('paid_at');

            $table->timestamps();

            $table->index(['portal_user_id', 'period_year', 'period_month']);
            $table->index(['plan_id', 'period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_payments');
    }
};
