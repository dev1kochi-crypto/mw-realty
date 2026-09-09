<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'description' => 'Get started at no cost',
                'features' => ['List up to 3 properties', 'Basic CRM access', 'Email support'],
                'price' => 0,
                'billing_cycle' => 'free',
                'property_limit' => 3,
                'is_popular' => false,
                'order_index' => 1,
            ],
            [
                'name' => 'Basic',
                'description' => 'For individual agents',
                'features' => ['List up to 15 properties', 'Full CRM access', 'Email & chat support', 'Featured listing (1/month)'],
                'price' => 49,
                'billing_cycle' => 'monthly',
                'property_limit' => 15,
                'is_popular' => false,
                'order_index' => 2,
            ],
            [
                'name' => 'Pro',
                'description' => 'For growing agencies',
                'features' => ['List up to 50 properties', 'Full CRM access', 'Priority support', 'Featured listings (5/month)', 'Team accounts'],
                'price' => 149,
                'billing_cycle' => 'monthly',
                'property_limit' => 50,
                'is_popular' => true,
                'order_index' => 3,
            ],
            [
                'name' => 'Enterprise',
                'description' => 'For large companies',
                'features' => ['Unlimited properties', 'Full CRM access', 'Dedicated account manager', 'Unlimited featured listings', 'Custom branding'],
                'price' => 499,
                'billing_cycle' => 'monthly',
                'property_limit' => null,
                'is_popular' => false,
                'order_index' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['translations->en->name' => $plan['name']],
                [
                    'translations' => [
                        'en' => [
                            'name' => $plan['name'],
                            'description' => $plan['description'],
                            'features' => $plan['features'],
                        ],
                    ],
                    'price' => $plan['price'],
                    'billing_cycle' => $plan['billing_cycle'],
                    'property_limit' => $plan['property_limit'],
                    'is_popular' => $plan['is_popular'],
                    'order_index' => $plan['order_index'],
                    'status' => true,
                ]
            );
        }
    }
}
