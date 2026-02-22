<?php

namespace App\Modules\CrossBorderIOR\Services;

use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Modules\CrossBorderIOR\Services\CourierBookingService;
use App\Modules\CrossBorderIOR\Services\GlobalCourierService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShipmentBatchService
{
    public function __construct(
        private CourierBookingService $courierBooking,
        private GlobalCourierService $globalCourier
    ) {}

    /**
     * Create a new shipment batch.
     */
    public function createBatch(string $carrier, string $origin = 'USA-NY', string $destination = 'BD-DAC'): object
    {
        $batchNumber = 'BATCH-' . now()->format('Ymd') . '-' . str_pad(
            DB::table('ior_shipment_batches')->count() + 1, 4, '0', STR_PAD_LEFT
        );

        $id = DB::table('ior_shipment_batches')->insertGetId([
            'batch_number'      => $batchNumber,
            'carrier'           => $carrier,
            'origin_warehouse'  => $origin,
            'destination'       => $destination,
            'status'            => 'pending',
            'order_count'       => 0,
            'total_weight_kg'   => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        Log::info("[Shipment Batch] Created batch {$batchNumber} via {$carrier}");

        return DB::table('ior_shipment_batches')->find($id);
    }

    /**
     * Add orders to a batch and calculate total weight.
     */
    public function addOrdersToBatch(int $batchId, array $orderIds): array
    {
        $batch = DB::table('ior_shipment_batches')->find($batchId);
        if (!$batch) {
            return ['success' => false, 'message' => 'Batch not found.'];
        }

        $orders = IorForeignOrder::whereIn('id', $orderIds)
            ->where('order_status', IorForeignOrder::STATUS_WAREHOUSE)
            ->get();

        if ($orders->isEmpty()) {
            return ['success' => false, 'message' => 'No eligible orders found at warehouse status.'];
        }

        $totalWeight = 0;
        foreach ($orders as $order) {
            $order->update([
                'shipment_batch_id' => $batchId,
                'order_status'      => IorForeignOrder::STATUS_SHIPPED,
                'dispatched_at'     => now(),
            ]);
            $totalWeight += $order->product_weight_kg;

            // Log milestone
            DB::table('ior_order_milestones')->insert([
                'order_id'   => $order->id,
                'status'     => 'dispatched',
                'location'   => $batch->origin_warehouse,
                'message_en' => "Your package has been dispatched from {$batch->origin_warehouse} via {$batch->carrier}.",
                'message_bn' => "আপনার পণ্যটি {$batch->origin_warehouse} থেকে {$batch->carrier}-এর মাধ্যমে পাঠানো হয়েছে।",
                'metadata'   => json_encode(['batch' => $batch->batch_number]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Update batch stats
        DB::table('ior_shipment_batches')
            ->where('id', $batchId)
            ->update([
                'order_count'    => DB::raw("order_count + {$orders->count()}"),
                'total_weight_kg'=> DB::raw("total_weight_kg + {$totalWeight}"),
                'updated_at'     => now(),
            ]);

        Log::info("[Shipment Batch] Added {$orders->count()} orders to batch #{$batch->batch_number}");

        return [
            'success'      => true,
            'orders_added' => $orders->count(),
            'total_weight'=> $totalWeight,
            'batch'        => DB::table('ior_shipment_batches')->find($batchId),
        ];
    }

    /**
     * Manifest a batch – book international courier and mark as manifested.
     */
    public function manifestBatch(int $batchId, ?string $masterTrackingNo = null): array
    {
        $batch = DB::table('ior_shipment_batches')->find($batchId);
        if (!$batch || $batch->status !== 'pending') {
            return ['success' => false, 'message' => 'Batch not found or not in pending state.'];
        }

        $trackingNo = $masterTrackingNo ?? strtoupper($batch->carrier) . '-' . now()->format('YmdHis');

        DB::table('ior_shipment_batches')
            ->where('id', $batchId)
            ->update([
                'status'             => 'manifested',
                'master_tracking_no' => $trackingNo,
                'dispatched_at'      => now(),
                'estimated_arrival'  => now()->addDays(7),
                'updated_at'         => now(),
            ]);

        // Update all orders in batch with international tracking
        IorForeignOrder::where('shipment_batch_id', $batchId)
            ->update([
                'intl_tracking_number' => $trackingNo,
                'intl_courier_code'    => $batch->carrier,
            ]);

        Log::info("[Shipment Batch] Manifested batch #{$batch->batch_number} → Tracking: {$trackingNo}");

        return [
            'success'            => true,
            'tracking_number'    => $trackingNo,
            'estimated_arrival'  => now()->addDays(7)->toDateString(),
            'batch'              => DB::table('ior_shipment_batches')->find($batchId),
        ];
    }

    /**
     * Update batch status through lifecycle.
     */
    public function updateBatchStatus(int $batchId, string $newStatus): array
    {
        $validTransitions = [
            'manifested' => 'in_transit',
            'in_transit' => 'customs',
            'customs'    => 'received',
        ];

        $batch = DB::table('ior_shipment_batches')->find($batchId);
        if (!$batch) {
            return ['success' => false, 'message' => 'Batch not found.'];
        }

        $expected = $validTransitions[$batch->status] ?? null;
        if ($expected !== $newStatus) {
            return ['success' => false, 'message' => "Cannot transition from {$batch->status} to {$newStatus}."];
        }

        $updates = ['status' => $newStatus, 'updated_at' => now()];

        if ($newStatus === 'customs') {
            $updates['customs_cleared_at'] = now();
        }
        if ($newStatus === 'received') {
            $updates['arrived_at'] = now();
        }

        DB::table('ior_shipment_batches')->where('id', $batchId)->update($updates);

        Log::info("[Shipment Batch] Batch #{$batch->batch_number} → {$newStatus}");

        return [
            'success' => true,
            'batch'   => DB::table('ior_shipment_batches')->find($batchId),
        ];
    }

    /**
     * List all batches with pagination.
     */
    public function listBatches(int $perPage = 15)
    {
        return DB::table('ior_shipment_batches')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get batch detail with associated orders.
     */
    public function getBatchDetail(int $batchId): ?array
    {
        $batch = DB::table('ior_shipment_batches')->find($batchId);
        if (!$batch) return null;

        $orders = IorForeignOrder::where('shipment_batch_id', $batchId)->get();

        return [
            'batch'  => $batch,
            'orders' => $orders,
        ];
    }
}
