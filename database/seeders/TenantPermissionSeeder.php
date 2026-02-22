<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissionsByGroup = [
            'Ecommerce' => [
                'view-products', 'manage-products',
                'view-orders', 'manage-orders',
                'view-customers', 'manage-customers',
                'manage-settings', 'view-reports'
            ],
            'CRM' => [
                'view-leads', 'manage-leads',
                'view-contacts', 'manage-contacts',
                'manage-marketing', 'view-crm-stats'
            ],
            'HRM' => [
                'view-staff', 'manage-staff',
                'manage-payroll', 'view-attendance',
                'manage-leave', 'view-hrm-reports'
            ],
            'Inventory' => [
                'view-inventory', 'manage-inventory',
                'view-stock-logs', 'manage-warehouses'
            ],
            'Finance' => [
                'view-transactions', 'manage-transactions',
                'view-ledger', 'manage-accounts',
                'view-finance-reports'
            ],
            'Manufacturing' => [
                'view-bom', 'manage-bom',
                'view-work-orders', 'manage-work-orders'
            ],
            'System' => [
                'manage-users', 'manage-roles',
                'view-audit-logs', 'manage-tenant-profile'
            ]
        ];

        foreach ($permissionsByGroup as $group => $permissions) {
            foreach ($permissions as $permission) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => $permission],
                    [
                        'group' => $group,
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        // Create Admin role and assign all permissions
        $roleId = DB::table('roles')->updateOrInsert(
            ['name' => 'admin'],
            [
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Get the role ID (whether created or existing)
        $roleId = DB::table('roles')->where('name', 'admin')->value('id');

        // Assign all permissions to admin role
        $allPermissions = DB::table('permissions')->pluck('id');
        
        foreach ($allPermissions as $permissionId) {
            DB::table('role_has_permissions')->updateOrInsert(
                ['permission_id' => $permissionId, 'role_id' => $roleId]
            );
        }
    }
}
