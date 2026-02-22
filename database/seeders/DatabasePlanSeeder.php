<?php

namespace Database\Seeders;

use App\Models\TenantDatabasePlan;
use Illuminate\Database\Seeder;

class DatabasePlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'storage_limit_gb' => 10,
                'max_tables' => 50,
                'max_connections' => 10,
                'price' => 0.00,
                'is_active' => true,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'storage_limit_gb' => 25,
                'max_tables' => 200,
                'max_connections' => 50,
                'price' => 99.00,
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'storage_limit_gb' => 50,
                'max_tables' => 500,
                'max_connections' => 100,
                'price' => 299.00,
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'storage_limit_gb' => 200,
                'max_tables' => null, // Unlimited
                'max_connections' => 250,
                'price' => 999.00,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            TenantDatabasePlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
