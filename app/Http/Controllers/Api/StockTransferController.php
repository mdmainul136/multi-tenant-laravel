<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\StockTransferItem;
use App\Models\Inventory\WarehouseInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    public function index(Request $request)
    {
        $transfers = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'creator'])
            ->orderByDesc('created_at')
            ->get();
        return response()->json(['success' => true, 'data' => $transfers]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'required|array',
        ]);

        return DB::transaction(function () use ($request) {
            $transfer = StockTransfer::create([
                'transfer_number' => StockTransfer::generateTransferNumber(),
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_warehouse_id' => $request->to_warehouse_id,
                'status' => 'pending',
                'created_by' => auth()->id() ?? User::first()->id, // Fallback for dev
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $item) {
                StockTransferItem::create([
                    'transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return response()->json(['success' => true, 'data' => $transfer]);
        });
    }

    public function dispatch(Request $request, $id)
    {
        $transfer = StockTransfer::findOrFail($id);
        if ($transfer->status !== 'pending') return response()->json(['success' => false, 'message' => 'Invalid status']);

        return DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $item) {
                // Deduct from source warehouse
                $sourceInv = WarehouseInventory::where('warehouse_id', $transfer->from_warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();
                
                if (!$sourceInv || $sourceInv->quantity < $item->quantity) {
                    throw new \Exception("Insufficient stock for product ID: {$item->product_id} in source warehouse");
                }
                
                $sourceInv->decrement('quantity', $item->quantity);
            }

            $transfer->update(['status' => 'in_transit', 'shipped_at' => now()]);
            return response()->json(['success' => true]);
        });
    }

    public function receive(Request $request, $id)
    {
        $transfer = StockTransfer::findOrFail($id);
        if ($transfer->status !== 'in_transit') return response()->json(['success' => false, 'message' => 'Invalid status']);

        return DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $item) {
                // Add to destination warehouse
                WarehouseInventory::updateOrCreate(
                    ['warehouse_id' => $transfer->to_warehouse_id, 'product_id' => $item->product_id],
                    ['quantity' => DB::raw("quantity + {$item->quantity}")]
                );
            }

            $transfer->update(['status' => 'completed', 'received_at' => now()]);
            return response()->json(['success' => true]);
        });
    }
}
