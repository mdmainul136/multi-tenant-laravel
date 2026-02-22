<?php

namespace Database\Seeders;

use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('admin123'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        SuperAdmin::create([
            'name' => 'Admin User',
            'email' => 'user@admin.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
