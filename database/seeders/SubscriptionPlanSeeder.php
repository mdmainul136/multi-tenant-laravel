<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter Plan',
                'plan_key' => 'starter',
                'description' => 'Perfect for small businesses starting their digital journey.',
                'price_monthly' => 29.00,
                'price_yearly' => 290.00,
                'currency' => 'USD',
                'is_active' => true,
                'features' => json_encode([
                    'Up to 100 products',
                    'Basic analytics',
                    'Single admin user',
                    'Community support',
                ]),
                'quotas' => json_encode([
                    'max_products' => 100,
                    'max_users' => 1,
                    'storage_gb' => 5,
                    'api_limit_daily' => 1000,
                ]),
            ],
            [
                'name' => 'Pro Plan',
                'plan_key' => 'pro',
                'description' => 'Advanced features for growing enterprises.',
                'price_monthly' => 79.00,
                'price_yearly' => 790.00,
                'currency' => 'USD',
                'is_active' => true,
                'features' => json_encode([
                    'Unlimited products',
                    'Advanced analytics & reports',
                    'Up to 5 admin users',
                    'Priority email support',
                    'Custom domain support',
                ]),
                'quotas' => json_encode([
                    'max_products' => -1, // Unlimited
                    'max_users' => 5,
                    'storage_gb' => 50,
                    'api_limit_daily' => 10000,
                ]),
            ],
            [
                'name' => 'Enterprise Plan',
                'plan_key' => 'enterprise',
                'description' => 'Scale your business with dedicated infrastructure and support.',
                'price_monthly' => 249.00,
                'price_yearly' => 2490.00,
                'currency' => 'USD',
                'is_active' => true,
                'features' => json_encode([
                    'Dedicated account manager',
                    'Custom integrations',
                    'Unlimited admin users',
                    '24/7 phone support',
                    'SLA guarantee',
                    'White-label options',
                ]),
                'quotas' => json_encode([
                    'max_products' => -1,
                    'max_users' => -1,
                    'storage_gb' => 500,
                    'api_limit_daily' => 100000,
                ]),
            ],
        ];

        foreach ($plans as $plan) {
            \App\Models\SubscriptionPlan::updateOrCreate(
                ['plan_key' => $plan['plan_key']],
                $plan
            );
        }
    }
}
