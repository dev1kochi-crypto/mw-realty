<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_payments', function (Blueprint $table) {
            // Invoice email is sent once per paid payment (webhooks can repeat).
            $table->dateTime('invoice_sent_at')->nullable()->after('attempts');
        });
    }

    public function down(): void
    {
        Schema::table('plan_payments', fn (Blueprint $table) => $table->dropColumn('invoice_sent_at'));
    }
};
