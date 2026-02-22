<?php

namespace App\Modules\Ecommerce\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ecommerce\Order;
use App\Models\Ecommerce\OrderItem;
use App\Models\Ecommerce\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with('customer')->latest();

        // Filter by order type (local vs cross_border)
        if ($type = $request->query('order_type')) {
            $query->where('order_type', $type);
        }

        $orders = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'nullable|exists:ec_customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:ec_products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'sometimes|string',
            'shipping_method' => 'sometimes|in:air,sea',
            'shipping_full_name' => 'nullable|string|max:255',
            'shipping_phone' => 'nullable|string|max:20',
            'shipping_address' => 'nullable|string',
            'shipping_city' => 'nullable|string|max:255',
            'shipping_postal_code' => 'nullable|string|max:20',
            'guest_email' => 'nullable|email|max:255',
        ]);

        try {
            $dto = \App\Modules\Ecommerce\DTOs\OrderDTO::fromRequest($request->all());
            $order = app(\App\Modules\Ecommerce\Actions\PlaceOrderAction::class)->execute($dto);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order,
                'is_cross_border' => $order->order_type === 'cross_border',
                'payment_notice' => ($order->order_type === 'cross_border' && $order->payment_status !== 'paid') ? 'This order requires advance payment.' : null
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function show($id)
    {
        $order = Order::with(['customer', 'items.product'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled,refunded',
        ]);

        $order = app(\App\Modules\Ecommerce\Actions\UpdateOrderStatusAction::class)->execute((int)$id, $request->status);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => $order
        ]);
    }

    /**
     * GET /orders/{id}/timeline
     */
    public function timeline($id)
    {
        $order = Order::findOrFail($id);

        // Fetch logs from ior_logs (which we now use for unified history)
        $logs = \DB::table('ior_logs')
            ->where('order_id', $order->id) // Assuming we link by ID, or order_number
            ->where('visible_to_customer', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($log) {
                $payload = json_decode($log->payload, true);
                return [
                    'event' => $log->event,
                    'description' => $this->getEventDescription($log->event, $payload),
                    'time' => $log->created_at,
                    'status' => $log->status
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    private function getEventDescription($event, $payload)
    {
        return match($event) {
            'order_placed' => 'Order was successfully placed.',
            'status_updated' => 'Order status updated to ' . ($payload['status'] ?? 'unknown') . '.',
            'payment_confirmed' => 'Payment has been confirmed. Thank you!',
            'price_recalculated' => 'Price was updated due to currency exchange rate change.',
            'shipped' => 'Order has been shipped. Tracking: ' . ($payload['tracking_number'] ?? 'N/A'),
            default => str_replace('_', ' ', ucfirst($event))
        };
    }
}
