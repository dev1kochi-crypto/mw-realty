<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 'checkout' = online Stripe payment started but not confirmed yet (see StripeBillingService).
        DB::statement("ALTER TABLE plan_upgrade_requests MODIFY status ENUM('pending', 'approved', 'rejected', 'checkout') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::table('plan_upgrade_requests')->where('status', 'checkout')->delete();
        DB::statement("ALTER TABLE plan_upgrade_requests MODIFY status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'");
    }
};
