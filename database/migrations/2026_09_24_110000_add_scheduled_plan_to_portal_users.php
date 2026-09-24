<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            // A downgrade chosen mid-period: the account keeps its current (paid-for) plan until
            // subscription_renews_at, then switches to this one on the next renewal.
            $table->foreignId('scheduled_plan_id')->nullable()->after('subscription_cancel_at_period_end')
                ->constrained('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('portal_users', fn (Blueprint $table) => $table->dropConstrainedForeignId('scheduled_plan_id'));
    }
};
