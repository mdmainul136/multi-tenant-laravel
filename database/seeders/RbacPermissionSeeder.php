<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacPermissionSeeder extends Seeder
{
    /**
     * Seed all module permissions and default roles.
     */
    public function run(): void
    {
        // ─── Ecommerce Module ────────────────────────────────
        Permission::seedForModule('ecommerce', 'products');
        Permission::seedForModule('ecommerce', 'orders');
        Permission::seedForModule('ecommerce', 'customers');
        Permission::seedForModule('ecommerce', 'coupons');
        Permission::seedForModule('ecommerce', 'categories');
        Permission::seedForModule('ecommerce', 'reviews', ['view', 'delete']);
        Permission::seedForModule('ecommerce', 'refunds', ['view', 'create']);

        // ─── POS Module ─────────────────────────────────────
        Permission::seedForModule('pos', 'sales');
        Permission::seedForModule('pos', 'products');
        Permission::seedForModule('pos', 'sessions', ['view', 'create']);
        Permission::seedForModule('pos', 'refunds', ['view', 'create']);

        // ─── Inventory Module ────────────────────────────────
        Permission::seedForModule('inventory', 'products');
        Permission::seedForModule('inventory', 'warehouses');
        Permission::seedForModule('inventory', 'stock_movements', ['view', 'create']);

        // ─── CRM Module ─────────────────────────────────────
        Permission::seedForModule('crm', 'contacts');
        Permission::seedForModule('crm', 'leads');
        Permission::seedForModule('crm', 'deals');

        // ─── HRM Module ─────────────────────────────────────
        Permission::seedForModule('hrm', 'employees');
        Permission::seedForModule('hrm', 'payroll', ['view', 'create']);
        Permission::seedForModule('hrm', 'attendance', ['view', 'create']);

        // ─── Finance Module ──────────────────────────────────
        Permission::seedForModule('finance', 'accounts');
        Permission::seedForModule('finance', 'transactions');
        Permission::seedForModule('finance', 'invoices');
        Permission::seedForModule('finance', 'reports', ['view']);

        // ─── Manufacturing Module ────────────────────────────
        Permission::seedForModule('manufacturing', 'bom');
        Permission::seedForModule('manufacturing', 'orders');

        // ─── Marketing Campaigns Module ────────────────────────
        Permission::seedForModule('marketing', 'campaigns');
        Permission::seedForModule('marketing', 'templates');
        Permission::seedForModule('marketing', 'audiences');

        // ─── Cross-Border IOR Module ─────────────────────────
        Permission::seedForModule('cross_border_ior', 'orders');
        Permission::seedForModule('cross_border_ior', 'products', ['view', 'create', 'update']);
        Permission::seedForModule('cross_border_ior', 'settings', ['view', 'update']);

        // ─── Tracking Module ─────────────────────────────────
        Permission::seedForModule('tracking', 'shipments');
        Permission::seedForModule('tracking', 'destinations', ['view', 'create', 'update']);

        // ─── RBAC / Admin ────────────────────────────────────
        Permission::seedForModule('admin', 'roles');
        Permission::seedForModule('admin', 'users');
        Permission::seedForModule('admin', 'activity_logs', ['view']);
        Permission::seedForModule('admin', 'settings', ['view', 'update']);

        // ═══════════════════════════════════════════════════════
        // DEFAULT ROLES
        // ═══════════════════════════════════════════════════════

        // Owner has all permissions
        $allPerms = Permission::pluck('name')->toArray();
        Role::createWithPermissions('owner', $allPerms, 'Full access to everything');
        Role::where('name', 'owner')->update(['is_system' => true]);

        // Super Admin
        Role::createWithPermissions('super-admin', $allPerms, 'System administrator');
        Role::where('name', 'super-admin')->update(['is_system' => true]);

        // Manager — most module access, no admin
        $managerPerms = Permission::where('group', '!=', 'admin')
            ->where('name', 'not like', '%.delete')
            ->pluck('name')->toArray();
        Role::createWithPermissions('manager', $managerPerms, 'Module manager without delete/admin access');

        // Cashier — POS only
        $cashierPerms = Permission::where('group', 'pos')->pluck('name')->toArray();
        Role::createWithPermissions('cashier', $cashierPerms, 'POS terminal operator');

        // Sales Rep — Ecommerce + CRM
        $salesPerms = Permission::whereIn('group', ['ecommerce', 'crm'])
            ->where('name', 'not like', '%.delete')
            ->pluck('name')->toArray();
        Role::createWithPermissions('sales-rep', $salesPerms, 'Sales representative');

        // Warehouse Staff — Inventory only
        $warehousePerms = Permission::where('group', 'inventory')->pluck('name')->toArray();
        Role::createWithPermissions('warehouse-staff', $warehousePerms, 'Warehouse operator');

        // Viewer — read-only
        $viewPerms = Permission::where('name', 'like', '%.view')->pluck('name')->toArray();
        $moduleAccess = Permission::where('name', 'like', 'module.%.access')->pluck('name')->toArray();
        Role::createWithPermissions('viewer', array_merge($viewPerms, $moduleAccess), 'Read-only access');
    }
}
