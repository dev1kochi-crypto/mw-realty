<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Accounts created without a plan (demo seeder, agency-created agents, older admin creates) showed
 * "No Plan". They move to the default Free plan — same entitlements in practice (no plan already
 * meant no new listing slots); existing listings are untouched. New accounts get Free automatically
 * (PortalUser::booted).
 */
return new class extends Migration
{
    public function up(): void
    {
        $free = DB::table('plans')->where('billing_cycle', 'free')->where('status', true)->orderBy('order_index')->value('id');
        if ($free) {
            DB::table('portal_users')->whereNull('plan_id')->update(['plan_id' => $free]);
        }
    }

    public function down(): void
    {
        // Not reversible: we can't tell which accounts had no plan before.
    }
};
