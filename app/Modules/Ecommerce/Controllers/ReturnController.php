<?php

namespace App\Modules\Ecommerce\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ecommerce\ReturnRequest;
use App\Models\Ecommerce\ReturnItem;
use App\Models\Ecommerce\Order;
use App\Models\Ecommerce\OrderItem;
use App\Models\Ecommerce\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    /**
     * List return requests with filters + pagination
     */
    public function index(Request $request)
    {
        $query = ReturnRequest::with(['order', 'customer']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }
        if ($request->filled('search')) {
            $query->where('return_number', 'LIKE', "%{$request->search}%");
        }

        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json([
            'success' => true,
            'data'    => $query->orderByDesc('created_at')->paginate($perPage),
        ]);
    }

    /**
     * KPI stats for returns dashboard
     */
    public function stats()
    {
        $now          = now();
        $totalOrders  = Order::count();
        $totalReturns = ReturnRequest::count();

        $refundMtd = ReturnRequest::where('status', 'refunded')
                                  ->whereMonth('resolved_at', $now->month)
                                  ->whereYear('resolved_at', $now->year)
                                  ->sum('refund_amount');

        return response()->json([
            'success' => true,
            'data'    => [
                'open_returns'   => ReturnRequest::open()->count(),
                'requested'      => ReturnRequest::where('status', 'requested')->count(),
                'approved'       => ReturnRequest::where('status', 'approved')->count(),
                'refund_mtd'     => (float) $refundMtd,
                'return_rate'    => $totalOrders > 0 ? round(($totalReturns / $totalOrders) * 100, 2) : 0,
                'total_refunded' => (float) ReturnRequest::where('status', 'refunded')->sum('refund_amount'),
            ],
        ]);
    }

    /**
     * Create return request from an existing order
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id'      => 'required|exists:tenant_dynamic.ec_orders,id',
            'type'          => 'required|in:refund,exchange',
            'reason'        => 'required|string|max:255',
            'reason_detail' => 'nullable|string',
            'refund_method' => 'nullable|in:original_payment,store_credit,bank_transfer,cash',
            'restock_items' => 'nullable|boolean',
            'items'         => 'required|array|min:1',
            'items.*.order_item_id' => 'nullable|exists:tenant_dynamic.ec_order_items,id',
            'items.*.product_id'    => 'nullable|exists:tenant_dynamic.ec_products,id',
            'items.*.product_name'  => 'required|string|max:255',
            'items.*.sku'           => 'nullable|string',
            'items.*.quantity'      => 'required|integer|min:1',
            'items.*.unit_price'    => 'required|numeric|min:0',
            'items.*.condition'     => 'nullable|in:new,used,damaged,defective',
        ]);

        $order = Order::findOrFail($validated['order_id']);

        return DB::transaction(function () use ($validated, $order) {
            // Calculate refund amount
            $refundAmount = array_sum(array_map(
                fn($i) => $i['quantity'] * $i['unit_price'],
                $validated['items']
            ));

            $returnRequest = ReturnRequest::create([
                'return_number' => ReturnRequest::generateReturnNumber(),
                'order_id'      => $order->id,
                'customer_id'   => $order->customer_id,
                'status'        => 'requested',
                'type'          => $validated['type'],
                'reason'        => $validated['reason'],
                'reason_detail' => $validated['reason_detail'] ?? null,
                'refund_method' => $validated['refund_method'] ?? 'original_payment',
                'refund_amount' => $refundAmount,
                'restock_items' => $validated['restock_items'] ?? true,
            ]);

            foreach ($validated['items'] as $item) {
                ReturnItem::create([
                    'return_id'     => $returnRequest->id,
                    'order_item_id' => $item['order_item_id'] ?? null,
                    'product_id'    => $item['product_id'] ?? null,
                    'product_name'  => $item['product_name'],
                    'sku'           => $item['sku'] ?? null,
                    'quantity'      => $item['quantity'],
                    'unit_price'    => $item['unit_price'],
                    'subtotal'      => $item['quantity'] * $item['unit_price'],
                    'condition'     => $item['condition'] ?? 'used',
                    'restocked'     => false,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Return request created',
                'data'    => $returnRequest->load('items'),
            ], 201);
        });
    }

    /**
     * Get return request with full details
     */
    public function show($id)
    {
        $return = ReturnRequest::with(['order.items', 'customer', 'items.product'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $return]);
    }

    /**
     * Approve a return request
     */
    public function approve(Request $request, $id)
    {
        $return = ReturnRequest::findOrFail($id);

        if (!$return->canTransitionTo('approved')) {
            return response()->json(['success' => false, 'message' => 'Cannot approve this return (status: ' . $return->status . ')'], 422);
        }

        $return->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'admin_note'  => $request->admin_note ?? $return->admin_note,
        ]);

        return response()->json(['success' => true, 'message' => 'Return approved', 'data' => $return->fresh()]);
    }

    /**
     * Reject a return request
     */
    public function reject(Request $request, $id)
    {
        $return = ReturnRequest::findOrFail($id);

        if (!$return->canTransitionTo('rejected')) {
            return response()->json(['success' => false, 'message' => 'Cannot reject this return'], 422);
        }

        $request->validate(['admin_note' => 'required|string|max:500']);

        $return->update([
            'status'      => 'rejected',
            'admin_note'  => $request->admin_note,
            'resolved_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Return rejected', 'data' => $return->fresh()]);
    }

    /**
     * Process refund — optionally restock returned items
     */
    public function processRefund(Request $request, $id)
    {
        $return = ReturnRequest::with('items')->findOrFail($id);

        if (!$return->canTransitionTo('refunded')) {
            return response()->json(['success' => false, 'message' => 'Cannot process refund (status: ' . $return->status . ')'], 422);
        }

        $request->validate([
            'refund_amount' => 'nullable|numeric|min:0',
            'refund_method' => 'nullable|in:original_payment,store_credit,bank_transfer,cash',
            'restock'       => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($request, $return) {
            $shouldRestock = $request->boolean('restock', $return->restock_items);

            // Restock items back to inventory
            if ($shouldRestock) {
                foreach ($return->items as $item) {
                    if ($item->product_id && !$item->restocked) {
                        Product::where('id', $item->product_id)
                               ->increment('stock_quantity', $item->quantity);
                        $item->update(['restocked' => true]);
                    }
                }
            }

            // Update order status to refunded if not exchanged
            if ($return->type === 'refund') {
                Order::where('id', $return->order_id)->update(['status' => 'refunded']);
            }

            $return->update([
                'status'        => 'refunded',
                'refund_amount' => $request->filled('refund_amount') ? $request->refund_amount : $return->refund_amount,
                'refund_method' => $request->filled('refund_method') ? $request->refund_method : $return->refund_method,
                'resolved_at'   => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'data'    => $return->fresh('items'),
            ]);
        });
    }
}
