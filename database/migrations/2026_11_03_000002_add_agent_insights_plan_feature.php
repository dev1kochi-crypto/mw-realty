<?php

use Database\Seeders\HelpGuideSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * New gated CRM section "Agent insights" (Plan::GATED_FEATURES `agent_insights`): an agency sees how
 * each of its agents handles the leads and listings assigned to them. Given to the plans that
 * already include every other gated section (Business and Enterprise — "everything" tier); others
 * can switch it on in CMS › Plans › Plan Features. Also adds its Help Desk guide.
 */
return new class extends Migration
{
    private const PREVIOUS_ALL = ['lead_insights', 'property_insights', 'listing_settings', 'facebook', 'property_finder'];

    public function up(): void
    {
        DB::table('plans')->where('billing_cycle', '!=', 'free')->get(['id', 'plan_features'])
            ->each(function ($plan) {
                $features = json_decode((string) $plan->plan_features, true) ?: [];
                if (array_diff(self::PREVIOUS_ALL, $features) || in_array('agent_insights', $features, true)) {
                    return;
                }
                DB::table('plans')->where('id', $plan->id)->update(['plan_features' => json_encode([...$features, 'agent_insights'])]);
            });

        (new HelpGuideSeeder)->run(); // adds only topics that don't exist yet
    }

    public function down(): void
    {
        DB::table('plans')->get(['id', 'plan_features'])->each(function ($plan) {
            $features = json_decode((string) $plan->plan_features, true) ?: [];
            if (in_array('agent_insights', $features, true)) {
                DB::table('plans')->where('id', $plan->id)->update(['plan_features' => json_encode(array_values(array_diff($features, ['agent_insights'])))]);
            }
        });
        DB::table('help_guides')->where('topic', 'agent-insights')->delete();
    }
};
