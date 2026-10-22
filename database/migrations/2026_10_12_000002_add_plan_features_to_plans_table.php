<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Keys of Plan::GATED_FEATURES this plan unlocks (lead insights, watermark, integrations…).
            $table->json('plan_features')->nullable()->after('reports_access');
        });

        // Paid plans get every gated section, free gets none.
        DB::table('plans')->where('billing_cycle', '!=', 'free')->update(['plan_features' => json_encode(array_keys(Plan::GATED_FEATURES))]);
    }

    public function down(): void
    {
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn('plan_features'));
    }
};
