<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\StockLog;
use App\Models\Inventory\WarehouseInventory;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * List warehouses.
     */
    public function warehouses()
    {
        return response()->json([
            'success' => true,
            'data' => Warehouse::all()
        ]);
    }

    /**
     * Create warehouse.
     */
    public function storeWarehouse(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:ec_warehouses,code',
            'location' => 'nullable|string',
            'is_default' => 'boolean'
        ]);

        $warehouse = Warehouse::create($validated);

        return response()->json(['success' => true, 'data' => $warehouse], 201);
    }

    /**
     * Check stock alerts.
     */
    public function alerts()
    {
        $alerts = $this->inventoryService->getLowStockItems();
        return response()->json([
            'success' => true,
            'data' => $alerts
        ]);
    }

    /**
     * Get stock history for a product.
     */
    public function history($productId)
    {
        $logs = StockLog::with('warehouse')
            ->where('product_id', $productId)
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $logs]);
    }

    /**
     * Manually adjust stock.
     */
    public function adjust(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:ec_products,id',
            'warehouse_id' => 'required|exists:ec_warehouses,id',
            'change' => 'required|integer',
            'type' => 'required|in:initial,adjustment,purchase,sale,return',
            'note' => 'nullable|string'
        ]);

        $inventory = $this->inventoryService->adjustStock(
            $request->product_id,
            $request->warehouse_id,
            $request->change,
            $request->type,
            ['note' => $request->note]
        );

        return response()->json(['success' => true, 'data' => $inventory]);
    }

    /**
     * Transfer stock between warehouses.
     */
    public function transfer(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:ec_products,id',
            'from_warehouse_id' => 'required|exists:ec_warehouses,id',
            'to_warehouse_id' => 'required|exists:ec_warehouses,id|different:from_warehouse_id',
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string'
        ]);

        $this->inventoryService->transferStock(
            $request->product_id,
            $request->from_warehouse_id,
            $request->to_warehouse_id,
            $request->quantity,
            $request->note
        );

        return response()->json(['success' => true, 'message' => 'Stock transferred successfully']);
    }
}
