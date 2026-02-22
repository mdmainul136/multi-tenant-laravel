<?php

namespace App\Modules\CrossBorderIOR\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Modules\CrossBorderIOR\Services\OrderNotificationService;
use App\Modules\CrossBorderIOR\Services\ProductPricingCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForeignOrderController extends Controller
{
    public function __construct(
        private ProductPricingCalculator $pricer,
        private OrderNotificationService $notifier,
    ) {}

    /**
     * GET /ior/orders
     */
    public function index(Request $request): JsonResponse
    {
        $query = IorForeignOrder::with('user');

        // Filters
        if ($status = $request->query('status')) {
            $query->where('order_status', $status);
        }
        if ($payment = $request->query('payment_status')) {
            $query->where('payment_status', $payment);
        }
        if ($marketplace = $request->query('marketplace')) {
            $query->where('source_marketplace', $marketplace);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%$search%")
                  ->orWhere('product_name', 'like', "%$search%")
                  ->orWhere('shipping_full_name', 'like', "%$search%")
                  ->orWhere('shipping_phone', 'like', "%$search%");
            });
        }

        $orders = $query->latest()->paginate($request->integer('per_page', 15));

        return response()->json(['success' => true, 'data' => $orders]);
    }

    /**
     * POST /ior/orders
     * Customer places an order (after getting quote from scraper).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_url'         => 'required|url|max:2000',
            'product_name'        => 'required|string|max:500',
            'quantity'            => 'integer|min:1|max:100',
            'product_variant'     => 'nullable|string|max:255',
            'product_image_url'   => 'nullable|url',
            'source_marketplace'  => 'nullable|in:amazon,alibaba,ebay,walmart,aliexpress,other',
            'source_price_usd'    => 'nullable|numeric|min:0',
            'shipping_method'     => 'in:air,sea',
            // Shipping
            'shipping_full_name'  => 'required|string|max:255',
            'shipping_phone'      => 'required|string|max:20',
            'shipping_address'    => 'required|string|max:500',
            'shipping_city'       => 'required|string|max:100',
            'shipping_area'       => 'nullable|string|max:100',
            // Payment
            'payment_method'      => 'required|in:bkash,sslcommerz,stripe,cod',
            // Scraped data cache
            'scraped_data'        => 'nullable|array',
        ]);

        $quantity = $data['quantity'] ?? 1;
        $shippingMethod = $data['shipping_method'] ?? 'air';

        // Calculate pricing
        $pricing = $this->pricer->calculate(
            usdPrice      : ($data['source_price_usd'] ?? 0) * $quantity,
            weightKg      : ($data['scraped_data']['weight_kg'] ?? 0.5) * $quantity,
            productTitle  : $data['product_name'],
            shippingMethod: $shippingMethod,
        );

        $order = IorForeignOrder::create([
            'user_id'             => $request->user()?->id,
            'product_url'         => $data['product_url'],
            'product_name'        => $data['product_name'],
            'quantity'            => $quantity,
            'product_variant'     => $data['product_variant'] ?? null,
            'product_image_url'   => $data['product_image_url'] ?? null,
            'source_marketplace'  => $data['source_marketplace'] ?? null,
            'source_price_usd'    => $data['source_price_usd'] ?? null,
            'exchange_rate'       => $pricing['exchange_rate'],
            'base_price_bdt'      => $pricing['base_price_bdt'],
            'customs_fee_bdt'     => $pricing['customs_fee_bdt'],
            'shipping_cost_bdt'   => $pricing['shipping_cost_bdt'],
            'profit_margin_bdt'   => $pricing['profit_margin_bdt'],
            'estimated_price_bdt' => $pricing['estimated_price_bdt'],
            'advance_amount'      => $pricing['advance_amount'],
            'remaining_amount'    => $pricing['remaining_amount'],
            'payment_method'      => $data['payment_method'],
            'payment_status'      => 'pending',
            'shipping_full_name'  => $data['shipping_full_name'],
            'shipping_phone'      => $data['shipping_phone'],
            'shipping_address'    => $data['shipping_address'],
            'shipping_city'       => $data['shipping_city'],
            'shipping_area'       => $data['shipping_area'] ?? null,
            'order_status'        => IorForeignOrder::STATUS_PENDING,
            'scraped_data'        => $data['scraped_data'] ?? null,
            'pricing_breakdown'   => $pricing,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully.',
            'data'    => [
                'order_id'      => $order->id,
                'order_number'  => $order->order_number,
                'advance_amount'=> $order->advance_amount,
                'payment_method'=> $order->payment_method,
                'order_status'  => $order->order_status,
                'pricing'       => $pricing,
            ],
        ], 201);
    }

    /**
     * GET /ior/orders/{id}
     */
    public function show(int $id): JsonResponse
    {
        $order = IorForeignOrder::with(['user', 'transactions'])->findOrFail($id);
        
        // Include milestones manually if no relation yet
        $milestones = \Illuminate\Support\Facades\DB::table('ior_order_milestones')
            ->where('order_id', $id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true, 
            'data' => array_merge($order->toArray(), ['milestones' => $milestones])
        ]);
    }

    /**
     * GET /ior/orders/{id}/proforma
     */
    public function proforma(int $id): JsonResponse
    {
        $order = IorForeignOrder::findOrFail($id);
        $service = app(\App\Modules\CrossBorderIOR\Services\ProformaInvoiceService::class);
        
        $breakdown = $service->generateBreakdown([
            'price_usd' => $order->source_price_usd,
            'hs_code'   => $order->scraped_data['hs_code'] ?? '8471.30.00',
            'weight_kg' => $order->getProductWeightKgAttribute(),
            'order_ref' => $order->order_number
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'breakdown' => $breakdown,
                'html'      => $service->getHtmlTemplate($breakdown)
            ]
        ]);
    }

    /**
     * PUT /ior/orders/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status'                => 'required|in:pending,sourcing,ordered,shipped,customs,delivered,cancelled',
            'tracking_number'       => 'nullable|string|max:100',
            'courier_code'          => 'nullable|string|max:50',
            'admin_note'            => 'nullable|string|max:1000',
            'cancellation_reason'   => 'nullable|string|max:500',
            'final_price_bdt'       => 'nullable|numeric|min:0',
        ]);

        $order = IorForeignOrder::findOrFail($id);

        $update = ['order_status' => $data['status']];
        if (isset($data['tracking_number']))     $update['tracking_number']     = $data['tracking_number'];
        if (isset($data['courier_code']))        $update['courier_code']        = $data['courier_code'];
        if (isset($data['admin_note']))          $update['admin_note']          = $data['admin_note'];
        if (isset($data['cancellation_reason'])) $update['cancellation_reason'] = $data['cancellation_reason'];
        if (isset($data['final_price_bdt']))     $update['final_price_bdt']     = $data['final_price_bdt'];

        $order->update($update);

        // Fire lifecycle notifications
        match ($data['status']) {
            'ordered'   => $this->notifier->sendConfirmation($order),
            'shipped'   => $this->notifier->sendShipped($order),
            'delivered' => $this->notifier->sendDelivered($order),
            'cancelled' => $this->notifier->sendCancelled($order, $data['cancellation_reason'] ?? ''),
            default     => null,
        };

        return response()->json([
            'success' => true,
            'message' => 'Order status updated to ' . $data['status'],
            'data'    => $order->fresh(),
        ]);
    }

    /**
     * PUT /ior/orders/{id}
     * Admin edits order details (pricing, shipping).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $order = IorForeignOrder::findOrFail($id);
        $data  = $request->validate([
            'product_name'       => 'sometimes|string|max:500',
            'quantity'           => 'sometimes|integer|min:1',
            'product_variant'    => 'nullable|string|max:255',
            'final_price_bdt'    => 'sometimes|numeric|min:0',
            'advance_amount'     => 'sometimes|numeric|min:0',
            'remaining_amount'   => 'sometimes|numeric|min:0',
            'advance_paid'       => 'sometimes|boolean',
            'remaining_paid'     => 'sometimes|boolean',
            'shipping_full_name' => 'sometimes|string|max:255',
            'shipping_phone'     => 'sometimes|string|max:20',
            'shipping_address'   => 'sometimes|string|max:500',
            'shipping_city'      => 'sometimes|string|max:100',
            'admin_note'         => 'nullable|string|max:1000',
        ]);

        // Auto-update payment_status based on paid flags
        if (isset($data['advance_paid']) || isset($data['remaining_paid'])) {
            $advPaid = $data['advance_paid']    ?? $order->advance_paid;
            $remPaid = $data['remaining_paid']  ?? $order->remaining_paid;
            $data['payment_status'] = match (true) {
                $advPaid && $remPaid => 'paid',
                $advPaid            => 'partial',
                default             => 'pending',
            };
        }

        $order->update($data);

        return response()->json(['success' => true, 'data' => $order->fresh()]);
    }

    /**
     * DELETE /ior/orders/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        IorForeignOrder::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Order deleted.']);
    }
}
