<?php

namespace App\Modules\CrossBorderIOR\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Modules\CrossBorderIOR\Services\CourierBookingService;
use App\Modules\CrossBorderIOR\Services\OrderNotificationService;
use App\Modules\CrossBorderIOR\Services\ShipmentBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IorWarehouseController extends Controller
{
    public function __construct(
        private OrderNotificationService $notifier,
        private ShipmentBatchService $batchService,
        private CourierBookingService $courierBooking
    ) {}

    // ════════════════════════════════════════
    // INBOUND (Existing)
    // ════════════════════════════════════════

    /**
     * POST /ior/warehouse/receive
     * Receive a product at the international warehouse.
     */
    public function receive(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => 'required|string',
            'location'   => 'nullable|string|max:100',
            'note'       => 'nullable|string|max:500',
        ]);

        $identifier = $data['identifier'];
        $location   = $data['location'] ?? 'International Warehouse';

        $order = IorForeignOrder::where('tracking_number', $identifier)
            ->orWhere('order_number', $identifier)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => "Order not found for identifier: {$identifier}"
            ], 404);
        }

        DB::beginTransaction();
        try {
            $oldStatus = $order->order_status;

            $order->update([
                'order_status' => IorForeignOrder::STATUS_WAREHOUSE,
                'admin_note'   => trim(($order->admin_note ?? '') . "\n[Warehouse] Received at {$location} on " . now()->toDateTimeString() . ". " . ($data['note'] ?? ''))
            ]);

            DB::table('ior_order_milestones')->insert([
                'order_id'   => $order->id,
                'status'     => 'warehouse_received',
                'location'   => $location,
                'message_en' => "Your package has arrived at our {$location} and is being prepared for international shipping.",
                'message_bn' => "আপনার পণ্যটি আমাদের {$location} ওয়্যারহাউসে পৌঁছেছে।",
                'metadata'   => json_encode(['worker_note' => $data['note'] ?? null, 'prev_status' => $oldStatus]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            if ($oldStatus !== IorForeignOrder::STATUS_WAREHOUSE) {
                $this->notifier->sendWarehouseArrival($order);
            }

            Log::info("[IOR Warehouse] Order {$order->order_number} received at {$location}");

            return response()->json([
                'success' => true,
                'message' => "Order {$order->order_number} marked as Arrived at Warehouse.",
                'data'    => $order->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[IOR Warehouse] Error receiving order: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ════════════════════════════════════════
    // OUTBOUND — Single Dispatch
    // ════════════════════════════════════════

    /**
     * POST /ior/warehouse/dispatch
     * Dispatch a single order from the warehouse (warehouse → shipped).
     */
    public function dispatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id'     => 'required|integer',
            'courier_code' => 'required|string', // fedex, dhl, etc.
            'location'     => 'nullable|string|max:100',
        ]);

        $order = IorForeignOrder::find($data['order_id']);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($order->order_status !== IorForeignOrder::STATUS_WAREHOUSE) {
            return response()->json([
                'success' => false,
                'message' => "Order must be in 'warehouse' status to dispatch. Current: {$order->order_status}"
            ], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Book courier
            $booking = $this->courierBooking->book($order, $data['courier_code']);

            // 2. Update order
            $order->update([
                'order_status'         => IorForeignOrder::STATUS_SHIPPED,
                'intl_courier_code'    => $data['courier_code'],
                'intl_tracking_number' => $booking['tracking_number'] ?? null,
                'dispatched_at'        => now(),
                'admin_note'           => trim(($order->admin_note ?? '') . "\n[Dispatch] Shipped via {$data['courier_code']} on " . now()->toDateTimeString())
            ]);

            // 3. Log milestone
            $location = $data['location'] ?? 'International Warehouse';
            DB::table('ior_order_milestones')->insert([
                'order_id'   => $order->id,
                'status'     => 'dispatched',
                'location'   => $location,
                'message_en' => "Your package has been dispatched from {$location} via " . strtoupper($data['courier_code']) . ".",
                'message_bn' => "আপনার পণ্যটি {$location} থেকে " . strtoupper($data['courier_code']) . "-এর মাধ্যমে পাঠানো হয়েছে।",
                'metadata'   => json_encode([
                    'courier'  => $data['courier_code'],
                    'tracking' => $booking['tracking_number'] ?? null,
                    'booking'  => $booking
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            // 4. Notify customer
            $this->notifier->sendShipped($order->fresh());

            Log::info("[IOR Dispatch] Order {$order->order_number} dispatched via {$data['courier_code']}");

            return response()->json([
                'success'  => true,
                'message'  => "Order {$order->order_number} dispatched.",
                'tracking' => $booking['tracking_number'] ?? null,
                'data'     => $order->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[IOR Dispatch] Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ════════════════════════════════════════
    // OUTBOUND — Batch Dispatch
    // ════════════════════════════════════════

    /**
     * POST /ior/warehouse/batch-dispatch
     * Create a batch and assign multiple orders for international shipping.
     */
    public function batchDispatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer',
            'carrier'     => 'required|string',
            'origin'      => 'nullable|string|max:50',
            'destination' => 'nullable|string|max:50',
        ]);

        try {
            // 1. Create batch
            $batch = $this->batchService->createBatch(
                $data['carrier'],
                $data['origin'] ?? 'USA-NY',
                $data['destination'] ?? 'BD-DAC'
            );

            // 2. Add orders to batch
            $result = $this->batchService->addOrdersToBatch($batch->id, $data['order_ids']);

            if (!$result['success']) {
                return response()->json($result, 422);
            }

            // 3. Manifest the batch
            $manifest = $this->batchService->manifestBatch($batch->id);

            // 4. Notify all dispatched orders
            $orders = IorForeignOrder::where('shipment_batch_id', $batch->id)->get();
            foreach ($orders as $order) {
                $this->notifier->sendShipped($order);
            }

            return response()->json([
                'success' => true,
                'message' => "Batch {$batch->batch_number} created with {$result['orders_added']} orders.",
                'data'    => [
                    'batch'    => $manifest['batch'] ?? $batch,
                    'tracking' => $manifest['tracking_number'] ?? null,
                    'orders'   => $result['orders_added'],
                    'weight'   => $result['total_weight'],
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("[IOR BatchDispatch] Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ════════════════════════════════════════
    // CUSTOMS CLEARANCE
    // ════════════════════════════════════════

    /**
     * POST /ior/warehouse/customs-clear
     * Mark an order as customs cleared.
     */
    public function customsClear(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|integer',
            'note'     => 'nullable|string|max:500',
        ]);

        $order = IorForeignOrder::find($data['order_id']);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if (!in_array($order->order_status, [IorForeignOrder::STATUS_SHIPPED, IorForeignOrder::STATUS_CUSTOMS])) {
            return response()->json([
                'success' => false,
                'message' => "Order must be in 'shipped' or 'customs' status. Current: {$order->order_status}"
            ], 422);
        }

        DB::beginTransaction();
        try {
            $order->update([
                'order_status'      => IorForeignOrder::STATUS_CUSTOMS,
                'customs_cleared_at'=> now(),
                'admin_note'        => trim(($order->admin_note ?? '') . "\n[Customs] Cleared on " . now()->toDateTimeString() . ". " . ($data['note'] ?? ''))
            ]);

            DB::table('ior_order_milestones')->insert([
                'order_id'   => $order->id,
                'status'     => 'customs_cleared',
                'location'   => 'Bangladesh Customs',
                'message_en' => "Your package has cleared Bangladesh customs and is ready for local delivery.",
                'message_bn' => "আপনার পণ্যটি বাংলাদেশ কাস্টমস থেকে ছাড় পেয়েছে এবং স্থানীয় ডেলিভারির জন্য প্রস্তুত।",
                'metadata'   => json_encode(['note' => $data['note'] ?? null]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            $this->notifier->sendCustomsCleared($order->fresh());

            Log::info("[IOR Customs] Order {$order->order_number} customs cleared.");

            return response()->json([
                'success' => true,
                'message' => "Order {$order->order_number} customs cleared.",
                'data'    => $order->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ════════════════════════════════════════
    // DELIVERY CONFIRMATION
    // ════════════════════════════════════════

    /**
     * POST /ior/warehouse/deliver
     * Mark order as delivered (final status).
     */
    public function deliver(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id'     => 'required|integer',
            'delivered_by' => 'nullable|string|max:100',
            'note'         => 'nullable|string|max:500',
        ]);

        $order = IorForeignOrder::find($data['order_id']);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($order->order_status !== IorForeignOrder::STATUS_CUSTOMS) {
            return response()->json([
                'success' => false,
                'message' => "Order must be in 'customs' status to deliver. Current: {$order->order_status}"
            ], 422);
        }

        DB::beginTransaction();
        try {
            $order->update([
                'order_status' => IorForeignOrder::STATUS_DELIVERED,
                'delivered_at' => now(),
                'admin_note'   => trim(($order->admin_note ?? '') . "\n[Delivered] " . now()->toDateTimeString() . " by " . ($data['delivered_by'] ?? 'courier') . ". " . ($data['note'] ?? ''))
            ]);

            DB::table('ior_order_milestones')->insert([
                'order_id'   => $order->id,
                'status'     => 'delivered',
                'location'   => $order->shipping_city ?? 'Customer Address',
                'message_en' => "Your package has been delivered! Thank you for shopping with us.",
                'message_bn' => "আপনার পণ্যটি সফলভাবে ডেলিভারি করা হয়েছে! আমাদের সাথে কেনাকাটা করার জন্য ধন্যবাদ।",
                'metadata'   => json_encode([
                    'delivered_by' => $data['delivered_by'] ?? null,
                    'note'         => $data['note'] ?? null
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            $this->notifier->sendDelivered($order->fresh());

            Log::info("[IOR Delivery] Order {$order->order_number} delivered.");

            return response()->json([
                'success' => true,
                'message' => "Order {$order->order_number} marked as Delivered.",
                'data'    => $order->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ════════════════════════════════════════
    // BATCH MANAGEMENT
    // ════════════════════════════════════════

    /**
     * GET /ior/shipment-batches
     */
    public function listBatches(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->batchService->listBatches()
        ]);
    }

    /**
     * GET /ior/shipment-batches/{id}
     */
    public function batchDetail(int $id): JsonResponse
    {
        $detail = $this->batchService->getBatchDetail($id);
        if (!$detail) {
            return response()->json(['success' => false, 'message' => 'Batch not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $detail]);
    }
}
