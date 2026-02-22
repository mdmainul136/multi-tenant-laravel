<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Inventory\{PurchaseOrder, PurchaseOrderItem};
use App\Models\Ecommerce\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function stats()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total'         => PurchaseOrder::count(),
                'pending'       => PurchaseOrder::pending()->count(),
                'received_mtd'  => PurchaseOrder::where('status', 'received')
                                    ->whereMonth('received_at', now()->month)->count(),
                'spend_mtd'     => (float) PurchaseOrder::where('status', 'received')
                                    ->whereMonth('received_at', now()->month)->sum('total_amount'),
                'by_status'     => PurchaseOrder::select('status', DB::raw('COUNT(*) as count'))
                                    ->groupBy('status')->pluck('count', 'status'),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $query = PurchaseOrder::with('supplier');
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('supplier_id')) $query->where('supplier_id', $request->supplier_id);
        return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->paginate(20)]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id'    => 'required|exists:tenant_dynamic.ec_suppliers,id',
            'items'          => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:tenant_dynamic.ec_products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.unit_cost'  => 'required|numeric|min:0',
            'tax_amount'     => 'nullable|numeric|min:0',
            'currency'       => 'nullable|string|max:10',
            'expected_date'  => 'nullable|date',
            'shipping_address'=> 'nullable|string',
            'notes'          => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, &$po) {
            $po = PurchaseOrder::create([
                'po_number'       => PurchaseOrder::generatePoNumber(),
                'supplier_id'     => $validated['supplier_id'],
                'status'          => 'draft',
                'tax_amount'      => $validated['tax_amount'] ?? 0,
                'currency'        => $validated['currency'] ?? 'USD',
                'expected_date'   => $validated['expected_date'] ?? null,
                'shipping_address'=> $validated['shipping_address'] ?? null,
                'notes'           => $validated['notes'] ?? null,
                'subtotal'        => 0,
                'total_amount'    => 0,
            ]);

            foreach ($validated['items'] as $item) {
                $subtotal = $item['quantity'] * $item['unit_cost'];
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id'        => $item['product_id'],
                    'quantity'          => $item['quantity'],
                    'unit_cost'         => $item['unit_cost'],
                    'subtotal'          => $subtotal,
                ]);
            }

            $po->recalculateTotal();
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase order created',
            'data'    => $po->load(['supplier', 'items.product']),
        ], 201);
    }

    public function show($id)
    {
        return response()->json([
            'success' => true,
            'data'    => PurchaseOrder::with(['supplier', 'items.product'])->findOrFail($id),
        ]);
    }

    public function update(Request $request, $id)
    {
        $po = PurchaseOrder::findOrFail($id);
        if (!in_array($po->status, ['draft'])) {
            return response()->json(['success' => false, 'message' => 'Only draft POs can be edited'], 422);
        }
        $po->update($request->validate([
            'expected_date'   => 'nullable|date',
            'shipping_address'=> 'nullable|string',
            'notes'           => 'nullable|string',
            'tax_amount'      => 'nullable|numeric|min:0',
        ]));
        return response()->json(['success' => true, 'data' => $po->fresh('supplier')]);
    }

    public function destroy($id)
    {
        $po = PurchaseOrder::findOrFail($id);
        if ($po->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Only draft POs can be deleted'], 422);
        }
        $po->items()->delete();
        $po->delete();
        return response()->json(['success' => true, 'message' => 'PO deleted']);
    }

    public function updateStatus(Request $request, $id)
    {
        $po = PurchaseOrder::findOrFail($id);
        $request->validate(['status' => 'required|in:' . implode(',', PurchaseOrder::STATUSES)]);

        if (!$po->canTransitionTo($request->status)) {
            return response()->json(['success' => false, 'message' => "Cannot move from '{$po->status}' to '{$request->status}'"], 422);
        }

        $extra = [];
        if ($request->status === 'sent')     $extra['sent_at']     = now();
        if ($request->status === 'received') $extra['received_at'] = now();

        $po->update(array_merge(['status' => $request->status], $extra));
        return response()->json(['success' => true, 'data' => $po->fresh('supplier')]);
    }

    /** Mark PO as received + auto-increment product stock */
    public function receive($id)
    {
        $po = PurchaseOrder::with('items.product')->findOrFail($id);

        if (!in_array($po->status, ['sent', 'partial'])) {
            return response()->json(['success' => false, 'message' => 'PO must be in sent/partial status to receive'], 422);
        }

        DB::transaction(function () use ($po) {
            foreach ($po->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock_quantity', $item->quantity);
                }
            }
            $po->update(['status' => 'received', 'received_at' => now()]);
        });

        return response()->json(['success' => true, 'message' => 'PO received and stock updated']);
    }
}
