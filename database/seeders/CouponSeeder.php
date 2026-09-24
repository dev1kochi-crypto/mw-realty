<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Demo coupons covering every option (percent/fixed, plan-restricted, once/repeating/forever,
 * scheduled, expired, usage-capped). Safe to re-run: matched on code.
 */
class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $planIds = fn (array $names) => Plan::whereIn('translations->en->name', $names)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $coupons = [
            [
                'code' => 'WELCOME20',
                'description' => 'Welcome offer — 20% off the first month of any paid plan',
                'discount_type' => 'percent', 'discount_value' => 20,
                'plan_ids' => null, 'duration' => 'once', 'duration_months' => null,
                'starts_at' => null, 'expires_at' => now()->addMonths(3)->endOfDay(),
                'max_uses' => null, 'max_uses_per_user' => 1, 'status' => true,
            ],
            [
                'code' => 'PRO3MONTHS',
                'description' => 'Pro plan launch — 30% off for 3 months',
                'discount_type' => 'percent', 'discount_value' => 30,
                'plan_ids' => $planIds(['Pro']), 'duration' => 'repeating', 'duration_months' => 3,
                'starts_at' => null, 'expires_at' => now()->addMonths(2)->endOfDay(),
                'max_uses' => 50, 'max_uses_per_user' => 1, 'status' => true,
            ],
            [
                'code' => 'AGENCY100',
                'description' => 'AED 100 off the first month of Business or Enterprise',
                'discount_type' => 'fixed', 'discount_value' => 100,
                'plan_ids' => $planIds(['Business', 'Enterprise']), 'duration' => 'once', 'duration_months' => null,
                'starts_at' => null, 'expires_at' => null,
                'max_uses' => 20, 'max_uses_per_user' => 1, 'status' => true,
            ],
            [
                'code' => 'BASIC10',
                'description' => 'Basic plan — AED 10 off every month',
                'discount_type' => 'fixed', 'discount_value' => 10,
                'plan_ids' => $planIds(['Basic']), 'duration' => 'forever', 'duration_months' => null,
                'starts_at' => null, 'expires_at' => null,
                'max_uses' => null, 'max_uses_per_user' => 1, 'status' => true,
            ],
            [
                'code' => 'PARTNER50',
                'description' => 'Partner agencies — 50% off for 6 months (limited to 10)',
                'discount_type' => 'percent', 'discount_value' => 50,
                'plan_ids' => $planIds(['Pro', 'Business', 'Enterprise']), 'duration' => 'repeating', 'duration_months' => 6,
                'starts_at' => null, 'expires_at' => now()->addYear()->endOfDay(),
                'max_uses' => 10, 'max_uses_per_user' => 1, 'status' => true,
            ],
            [
                'code' => 'NEWYEAR25',
                'description' => 'New Year campaign — 25% off the first month (starts 1 Jan)',
                'discount_type' => 'percent', 'discount_value' => 25,
                'plan_ids' => null, 'duration' => 'once', 'duration_months' => null,
                'starts_at' => now()->addYear()->startOfYear(), 'expires_at' => now()->addYear()->startOfYear()->addDays(14)->endOfDay(),
                'max_uses' => 100, 'max_uses_per_user' => 1, 'status' => true,
            ],
            [
                'code' => 'SUMMER15',
                'description' => 'Summer campaign — ended',
                'discount_type' => 'percent', 'discount_value' => 15,
                'plan_ids' => null, 'duration' => 'once', 'duration_months' => null,
                'starts_at' => now()->subMonths(4)->startOfDay(), 'expires_at' => now()->subMonths(1)->endOfDay(),
                'max_uses' => null, 'max_uses_per_user' => 1, 'status' => true,
            ],
            [
                'code' => 'VIPFREEMONTH',
                'description' => 'VIP — first month free on Pro (switched off until needed)',
                'discount_type' => 'percent', 'discount_value' => 100,
                'plan_ids' => $planIds(['Pro']), 'duration' => 'once', 'duration_months' => null,
                'starts_at' => null, 'expires_at' => null,
                'max_uses' => 5, 'max_uses_per_user' => 1, 'status' => false,
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::updateOrCreate(['code' => $coupon['code']], $coupon);
        }
    }
}
