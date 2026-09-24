<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Pricing tiers. Listings / featured quota / team agents / reports are real, enforced limits
 * (see Plan::entitlementLines()) and are rendered on the pricing cards automatically, so
 * `features` only lists the extras that aren't enforced in code (CRM level, support, ...).
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'description' => 'Get started at no cost',
                'features' => ['Basic CRM access', 'Email & chat support'],
                'price' => 0,
                'yearly_price' => null,
                'billing_cycle' => 'free',
                'property_limit' => 3,
                'featured_per_month' => 0,
                'featured_max_days' => null,
                'featured_period' => 'concurrent',
                'agent_limit' => 0,
                'reports_access' => false,
                'is_popular' => false,
                'order_index' => 1,
            ],
            [
                'name' => 'Basic',
                'description' => 'For individual agents',
                'features' => ['Full CRM access', 'Email support'],
                'price' => 49,
                'yearly_price' => 490,
                'billing_cycle' => 'monthly',
                'property_limit' => 15,
                'featured_per_month' => 1,
                'featured_max_days' => 5,
                'featured_period' => 'concurrent',
                'agent_limit' => 0,
                'reports_access' => true,
                'is_popular' => false,
                'order_index' => 2,
            ],
            [
                'name' => 'Pro',
                'description' => 'For growing agencies',
                'features' => ['Full CRM access', 'Priority support'],
                'price' => 149,
                'yearly_price' => 1490,
                'billing_cycle' => 'monthly',
                'property_limit' => 50,
                'featured_per_month' => 3,
                'featured_max_days' => 15,
                'featured_period' => 'concurrent',
                'agent_limit' => 3,
                'reports_access' => true,
                'is_popular' => true,
                'order_index' => 3,
            ],
            [
                'name' => 'Business',
                'description' => 'For established agencies with a team',
                'features' => ['Full CRM access', 'Priority support'],
                'price' => 299,
                'yearly_price' => 2990,
                'billing_cycle' => 'monthly',
                'property_limit' => 100,
                'featured_per_month' => 5,
                'featured_max_days' => 20,
                'featured_period' => 'concurrent',
                'agent_limit' => 10,
                'reports_access' => true,
                'is_popular' => false,
                'order_index' => 4,
            ],
            [
                'name' => 'Enterprise',
                'description' => 'For large companies',
                'features' => ['Full CRM access', 'Dedicated account manager', 'Custom branding'],
                'price' => 499,
                'yearly_price' => 4990,
                'billing_cycle' => 'monthly',
                'property_limit' => null,
                'featured_per_month' => 10,
                'featured_max_days' => null,
                'featured_period' => 'month',
                'agent_limit' => null,
                'reports_access' => true,
                'is_popular' => false,
                'order_index' => 5,
            ],
        ];

        foreach ($plans as $plan) {
            $existing = Plan::where('translations->en->name', $plan['name'])->first();
            // Keep any other languages an admin already filled in.
            $translations = $existing?->translations ?? [];
            $translations['en'] = [
                'name' => $plan['name'],
                'description' => $plan['description'],
                'features' => $plan['features'],
            ];

            Plan::updateOrCreate(
                ['translations->en->name' => $plan['name']],
                [
                    'translations' => $translations,
                    'price' => $plan['price'],
                    'yearly_price' => $plan['yearly_price'],
                    'billing_cycle' => $plan['billing_cycle'],
                    'property_limit' => $plan['property_limit'],
                    'featured_per_month' => $plan['featured_per_month'],
                    'featured_max_days' => $plan['featured_max_days'],
                    'featured_period' => $plan['featured_period'],
                    'agent_limit' => $plan['agent_limit'],
                    'reports_access' => $plan['reports_access'],
                    'is_popular' => $plan['is_popular'],
                    'order_index' => $plan['order_index'],
                    'status' => true,
                ]
            );
        }
    }
}
