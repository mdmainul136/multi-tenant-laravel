<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed the tenant's database.
     */
    public function run(): void
    {
        $this->call([
            TenantPermissionSeeder::class,
            // Add other tenant seeders here (e.g., initial categories, settings)
        ]);
    }
}
