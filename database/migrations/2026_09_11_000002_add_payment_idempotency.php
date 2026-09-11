<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $keys = [];
        DB::table('plan_payments')->orderBy('id')->chunkById(500, function ($rows) use (&$keys) {
            foreach ($rows as $row) {
                $key = \App\Services\PaymentRecorder::key($row->portal_user_id, $row->plan_id, $row->billing_cycle, $row->period_year, $row->period_month);
                if (isset($keys[$key])) {
                    throw new RuntimeException('Duplicate payment periods require reconciliation before migrating.');
                }
                $keys[$key] = $row->id;
            }
        });
        Schema::table('plan_payments', fn (Blueprint $t) => $t->string('idempotency_key', 120)->nullable()->unique());
        foreach ($keys as $key => $id) {
            DB::table('plan_payments')->where('id', $id)->update(['idempotency_key' => $key]);
        }
    }

    public function down(): void
    {
        Schema::table('plan_payments', function (Blueprint $t) {
            $t->dropUnique(['idempotency_key']);
            $t->dropColumn('idempotency_key');
        });
    }
};
