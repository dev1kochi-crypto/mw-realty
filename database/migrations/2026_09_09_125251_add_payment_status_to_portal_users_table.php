<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->enum('payment_status', ['paid', 'unpaid'])->default('unpaid')->after('plan_id');
            $table->date('last_payment_at')->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'last_payment_at']);
        });
    }
};
