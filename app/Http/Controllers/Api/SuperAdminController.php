<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Module;
use App\Models\TenantModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminController extends Controller
{
    /**
     * Dashboard statistics
     */
    public function dashboard()
    {
        $stats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('status', 'active')->count(),
            'total_modules' => Module::count(),
            'active_modules' => Module::where('is_active', true)->count(),
            'total_subscriptions' => TenantModule::where('status', 'active')->count(),
            'total_revenue' => $this->calculateTotalRevenue(),
            'monthly_revenue' => $this->calculateMonthlyRevenue(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get all tenants with pagination
     */
    public function tenants(Request $request)
    {
        $query = Tenant::query();

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('tenant_id', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $tenants = $query->with('tenantModules.module')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'data' => $tenants
        ]);
    }

    /**
     * Get single tenant details
     */
    public function tenantDetails($id)
    {
        $tenant = Tenant::with(['tenantModules.module'])->findOrFail($id);

        $subscriptions = TenantModule::where('tenant_id', $id)
            ->with('module')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => $tenant,
                'subscriptions' => $subscriptions,
                'total_spent' => $this->calculateTenantSpending($id),
            ]
        ]);
    }

    /**
     * Approve tenant
     */
    public function approveTenant($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'message' => 'Tenant approved successfully'
        ]);
    }

    /**
     * Suspend tenant
     */
    public function suspendTenant($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['status' => 'suspended']);

        return response()->json([
            'success' => true,
            'message' => 'Tenant suspended successfully'
        ]);
    }

    /**
     * Delete tenant
     */
    public function deleteTenant($id)
    {
        $tenant = Tenant::findOrFail($id);
        
        // Drop tenant database
        DB::statement("DROP DATABASE IF EXISTS {$tenant->database_name}");
        
        // Delete tenant record
        $tenant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tenant deleted successfully'
        ]);
    }

    /**
     * Calculate total revenue
     */
    private function calculateTotalRevenue()
    {
        return TenantModule::where('status', 'active')
            ->join('modules', 'tenant_modules.module_id', '=', 'modules.id')
            ->sum('modules.price');
    }

    /**
     * Calculate monthly revenue
     */
    private function calculateMonthlyRevenue()
    {
        return TenantModule::where('status', 'active')
            ->whereMonth('subscribed_at', now()->month)
            ->join('modules', 'tenant_modules.module_id', '=', 'modules.id')
            ->sum('modules.price');
    }

    /**
     * Calculate tenant spending
     */
    private function calculateTenantSpending($tenantId)
    {
        return TenantModule::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->join('modules', 'tenant_modules.module_id', '=', 'modules.id')
            ->sum('modules.price');
    }
}
