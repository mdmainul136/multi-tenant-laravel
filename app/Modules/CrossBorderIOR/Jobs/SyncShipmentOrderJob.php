<?php

namespace App\Modules\CrossBorderIOR\Jobs;

use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Modules\CrossBorderIOR\Services\CourierTrackingService;
use App\Modules\CrossBorderIOR\Services\OrderNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SyncShipmentOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public IorForeignOrder $order)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(
        CourierTrackingService $trackingService,
        OrderNotificationService $notificationService
    ): void {
        if (empty($this->order->tracking_number)) {
            return;
        }

        Log::info("[IOR Sync Job] Polling status for {$this->order->order_number} ({$this->order->tracking_number})");

        $result = $trackingService->track($this->order->tracking_number, $this->order->courier_code);

        if (!$result['success']) {
            Log::warning("[IOR Sync Job] Tracking failed for {$this->order->order_number}: " . ($result['message'] ?? 'Unknown error'));
            return;
        }

        $newStatus = $result['status'] ?? 'Unknown';
        $isDelivered = $result['is_delivered'] ?? false;

        // Determine if internal status needs to change
        $oldInternalStatus = $this->order->order_status;
        $newInternalStatus = $oldInternalStatus;

        if ($isDelivered) {
            $newInternalStatus = IorForeignOrder::STATUS_DELIVERED;
        } elseif ($oldInternalStatus === IorForeignOrder::STATUS_ORDERED) {
            // If it was just "ordered" but now we see courier activity, move to "shipped"
            $newInternalStatus = IorForeignOrder::STATUS_SHIPPED;
        }

        // Update if status changed
        if ($newInternalStatus !== $oldInternalStatus) {
            $this->order->order_status = $newInternalStatus;
            $this->order->save();

            Log::info("[IOR Sync Job] Status updated for {$this->order->order_number}: $oldInternalStatus -> $newInternalStatus");

            // Log event
            DB::table('ior_logs')->insert([
                'order_id'   => $this->order->id,
                'event'      => 'status_update',
                'payload'    => json_encode([
                    'from' => $oldInternalStatus,
                    'to' => $newInternalStatus,
                    'courier_status' => $newStatus,
                    'is_automated' => true
                ]),
                'status'     => 'success',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Send notification
            if ($newInternalStatus === IorForeignOrder::STATUS_SHIPPED) {
                $notificationService->sendShipped($this->order);
            } elseif ($newInternalStatus === IorForeignOrder::STATUS_DELIVERED) {
                $notificationService->sendDelivered($this->order);
            }
        } else {
            // Even if internal status didn't change, log the poll if there's new info?
            // Usually too noisy, but we can log that we checked.
            Log::info("[IOR Sync Job] No status change for {$this->order->order_number} (Current: $newStatus)");
        }
    }
}
