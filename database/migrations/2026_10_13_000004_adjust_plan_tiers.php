<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plan tiers as agreed with the business (paid plans, cheapest first):
 *   Basic       — watermark + Property Finder; no website ads
 *   Pro         — watermark + lead insights + Property Finder
 *   Business    — everything
 *   Enterprise  — everything
 * Website ads keep their allowance on Pro and up (Basic gets none). Editable in CMS › Plans.
 */
return new class extends Migration
{
    private const ALL = ['lead_insights', 'property_insights', 'listing_settings', 'facebook', 'property_finder'];

    public function up(): void
    {
        $tiers = [
            ['features' => ['listing_settings', 'property_finder'], 'ads' => [0, 0]],
            ['features' => ['listing_settings', 'lead_insights', 'property_finder'], 'ads' => null],
            ['features' => self::ALL, 'ads' => null],
            ['features' => self::ALL, 'ads' => null],
        ];

        DB::table('plans')->where('billing_cycle', '!=', 'free')->orderBy('order_index')->pluck('id')->values()
            ->each(function ($id, $i) use ($tiers) {
                $tier = $tiers[min($i, count($tiers) - 1)];
                DB::table('plans')->where('id', $id)->update(['plan_features' => json_encode($tier['features'])]
                    + ($tier['ads'] ? ['ads_per_month' => $tier['ads'][0], 'ad_max_hours' => $tier['ads'][1]] : []));
            });
    }

    public function down(): void
    {
        // Data change only; set plans back by hand in CMS › Plans if needed.
    }
};
