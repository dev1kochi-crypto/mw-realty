<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 2026_10_12_000002 gave every paid plan every gated CRM section, so Basic … Enterprise showed the
 * same feature list. Tier them, cheapest paid plan first:
 *   Basic       — listing settings (watermark)
 *   Pro         — + lead insights, property insights
 *   Business    — + Facebook Lead Ads integration
 *   Enterprise  — + Property Finder integration (everything)
 * Only plans still on that blanket default are changed — a plan Super Admin already edited is kept.
 * Adjust any time in CMS › Plans › Plan Features.
 */
return new class extends Migration
{
    private const ALL = ['lead_insights', 'property_insights', 'listing_settings', 'facebook', 'property_finder'];

    private const TIERS = [
        ['listing_settings'],
        ['listing_settings', 'lead_insights', 'property_insights'],
        ['listing_settings', 'lead_insights', 'property_insights', 'facebook'],
        ['listing_settings', 'lead_insights', 'property_insights', 'facebook', 'property_finder'],
    ];

    public function up(): void
    {
        DB::table('plans')->where('billing_cycle', '!=', 'free')->orderBy('order_index')->get(['id', 'plan_features'])
            ->values()
            ->each(function ($plan, $i) {
                $current = json_decode((string) $plan->plan_features, true) ?: [];
                if (array_diff(self::ALL, $current) || array_diff($current, self::ALL)) {
                    return; // customised already
                }
                DB::table('plans')->where('id', $plan->id)->update([
                    'plan_features' => json_encode(self::TIERS[min($i, count(self::TIERS) - 1)]),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('plans')->where('billing_cycle', '!=', 'free')->update(['plan_features' => json_encode(self::ALL)]);
    }
};
