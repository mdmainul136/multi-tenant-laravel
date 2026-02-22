<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\DB;

class IorMilestoneService
{
    /**
     * Add a new milestone to an order.
     */
    public function addMilestone(int $orderId, string $status, string $messageEn, ?string $location = null, array $metadata = []): void
    {
        DB::table('ior_order_milestones')->insert([
            'order_id'   => $orderId,
            'status'     => $status,
            'message_en' => $messageEn,
            'location'   => $location,
            'metadata'   => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update main order status if necessary
        if (DB::hasTable('ec_orders')) {
            DB::table('ec_orders')->where('id', $orderId)->update([
                'status' => $status,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Get lifecycle for an order.
     */
    public function getHistory(int $orderId): array
    {
        return DB::table('ior_order_milestones')
            ->where('order_id', $orderId)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }
}
