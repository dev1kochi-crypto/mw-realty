<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gated CRM section "Market Demand" (Plan::GATED_FEATURES `market_demand`) was added without
 * switching it on for any plan, so it stayed padlocked even on Enterprise. Given to the plans that
 * already include every other gated section (Business and Enterprise, the "everything" tier); others
 * can switch it on in CMS › Plans › Plan Features.
 */
return new class extends Migration
{
    private const PREVIOUS_ALL = ['lead_insights', 'property_insights', 'listing_settings', 'facebook', 'property_finder', 'agent_insights'];

    public function up(): void
    {
        DB::table('plans')->where('billing_cycle', '!=', 'free')->get(['id', 'plan_features'])
            ->each(function ($plan) {
                $features = json_decode((string) $plan->plan_features, true) ?: [];
                if (array_diff(self::PREVIOUS_ALL, $features) || in_array('market_demand', $features, true)) {
                    return;
                }
                DB::table('plans')->where('id', $plan->id)->update(['plan_features' => json_encode([...$features, 'market_demand'])]);
            });
    }

    public function down(): void
    {
        DB::table('plans')->get(['id', 'plan_features'])->each(function ($plan) {
            $features = json_decode((string) $plan->plan_features, true) ?: [];
            if (in_array('market_demand', $features, true)) {
                DB::table('plans')->where('id', $plan->id)->update(['plan_features' => json_encode(array_values(array_diff($features, ['market_demand'])))]);
            }
        });
    }
};
