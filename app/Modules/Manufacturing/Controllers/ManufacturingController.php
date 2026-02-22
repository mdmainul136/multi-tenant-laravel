<?php

namespace App\Modules\Manufacturing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\ManufacturingOrder;
use App\Modules\Manufacturing\Services\ManufacturingService;
use Illuminate\Http\Request;

class ManufacturingController extends Controller
{
    protected ManufacturingService $manufacturingService;

    public function __construct(ManufacturingService $manufacturingService)
    {
        $this->manufacturingService = $manufacturingService;
    }

    /**
     * List all BOMs.
     */
    public function boms()
    {
        return response()->json([
            'success' => true,
            'data' => Bom::with(['finishedProduct', 'items.rawMaterial'])->get()
        ]);
    }

    /**
     * Create BOM.
     */
    public function storeBom(Request $request)
    {
        $request->validate([
            'finished_product_id' => 'required|exists:ec_products,id',
            'name' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:ec_products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001'
        ]);

        $dto = \App\Modules\Manufacturing\DTOs\BomDTO::fromRequest($request->all());
        $bom = app(\App\Modules\Manufacturing\Actions\CreateBomAction::class)->execute($dto);

        return response()->json(['success' => true, 'data' => $bom], 201);
    }

    /**
     * List Manufacturing Orders.
     */
    public function orders(Request $request)
    {
        $query = ManufacturingOrder::with(['finishedProduct', 'warehouse', 'bom'])->latest();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json(['success' => true, 'data' => $query->paginate(15)]);
    }

    /**
     * Create a new Manufacturing Order.
     */
    public function storeOrder(Request $request)
    {
        $request->validate([
            'finished_product_id' => 'required|exists:ec_products,id',
            'bom_id' => 'required|exists:ec_bom,id',
            'warehouse_id' => 'required|exists:ec_warehouses,id',
            'target_quantity' => 'required|integer|min:1',
        ]);

        $mo = ManufacturingOrder::create([
            'order_number' => 'MO-' . strtoupper(uniqid()),
            'finished_product_id' => $request->finished_product_id,
            'bom_id' => $request->bom_id,
            'warehouse_id' => $request->warehouse_id,
            'target_quantity' => $request->target_quantity,
            'status' => 'planned',
            'user_id' => auth()->id() ?? 1,
        ]);

        return response()->json(['success' => true, 'data' => $mo], 201);
    }

    /**
     * Start production.
     */
    public function startOrder($id)
    {
        try {
            $mo = app(\App\Modules\Manufacturing\Actions\StartManufacturingOrderAction::class)->execute((int)$id);
            return response()->json(['success' => true, 'message' => 'Production started successfully', 'data' => $mo]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Complete production.
     */
    public function completeOrder(Request $request, $id)
    {
        $request->validate(['produced_quantity' => 'required|integer|min:1']);

        try {
            $mo = app(\App\Modules\Manufacturing\Actions\CompleteManufacturingOrderAction::class)->execute((int)$id, (int)$request->produced_quantity);
            return response()->json(['success' => true, 'message' => 'Production completed and stock updated', 'data' => $mo]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    /**
     * Get manufacturing statistics for dashboard.
     */
    public function stats()
    {
        $activeOrders = ManufacturingOrder::whereIn('status', ['planned', 'in-progress'])->count();
        $completedToday = ManufacturingOrder::where('status', 'completed')
            ->whereDate('updated_at', now()->toDateString())
            ->count();
        
        return response()->json([
            'success' => true,
            'data' => [
                'active_orders' => $activeOrders,
                'completed_today' => $completedToday,
                'raw_materials_low' => 0,
                'efficiency' => '98%',
            ]
        ]);
    }
}
