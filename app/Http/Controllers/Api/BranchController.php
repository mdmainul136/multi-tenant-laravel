<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Models\POS\PosSale;
use App\Models\Inventory\WarehouseInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchController extends Controller
{
    /**
     * List all branches for the tenant
     */
    public function index(Request $request)
    {
        $tenantId = $request->input('token_tenant_id');
        $branches = Branch::where('tenant_id', $tenantId)
            ->withCount(['users', 'sales'])
            ->get();

        return response()->json(['success' => true, 'data' => $branches]);
    }

    /**
     * Create a new branch
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:branches,code',
        ]);

        $tenantId = $request->input('token_tenant_id');
        $branch = Branch::create(array_merge($request->all(), ['tenant_id' => $tenantId]));

        return response()->json(['success' => true, 'data' => $branch]);
    }

    /**
     * Assign staff to a branch
     */
    public function assignStaff(Request $request, $id)
    {
        $branch = Branch::findOrFail($id);
        $userIds = $request->input('user_ids', []);

        User::whereIn('id', $userIds)->update(['branch_id' => $branch->id]);

        return response()->json(['success' => true, 'message' => 'Staff assigned successfully']);
    }

    /**
     * Get branch performance statistics
     */
    public function performance(Request $request, $id)
    {
        $branch = Branch::findOrFail($id);
        
        $salesStats = PosSale::where('branch_id', $id)
            ->select(DB::raw('COUNT(*) as total_orders'), DB::raw('SUM(total) as total_revenue'))
            ->first();

        // Top selling products in this branch
        $topProducts = DB::table('pos_sale_items')
            ->join('pos_sales', 'pos_sale_items.sale_id', '=', 'pos_sales.id')
            ->where('pos_sales.branch_id', $id)
            ->select('product_id', DB::raw('SUM(quantity) as total_qty'))
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'branch' => $branch,
                'revenue' => $salesStats->total_revenue ?? 0,
                'orders' => $salesStats->total_orders ?? 0,
                'top_products' => $topProducts
            ]
        ]);
    }

    /**
     * Get branch inventory
     */
    public function inventory(Request $request, $id)
    {
        $branch = Branch::with('warehouses')->findOrFail($id);
        $warehouseIds = $branch->warehouses->pluck('id');

        $inventory = WarehouseInventory::whereIn('warehouse_id', $warehouseIds)
            ->with('product')
            ->get();

        return response()->json(['success' => true, 'data' => $inventory]);
    }
}
