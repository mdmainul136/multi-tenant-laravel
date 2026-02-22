<?php

namespace App\Modules\CrossBorderIOR\Services;

use App\Models\CrossBorderIOR\IorForeignOrder;
use Illuminate\Support\Facades\Log;

/**
 * OrderMilestoneService
 * 
 * Central switch for updating order status and firing notifications.
 */
class OrderMilestoneService
{
    public function __construct(
        private OrderNotificationService $notifier
    ) {}

    /**
     * Update order status based on courier movement.
     */
    public function updateFromCourier(IorForeignOrder $order, string $courierStatus, string $provider): void
    {
        Log::info("[Milestone] Processing courier status '{$courierStatus}' for order {$order->order_number}");

        $newStatus = $this->mapCourierStatus($courierStatus, $provider);

        if ($newStatus && $order->order_status !== $newStatus) {
            $oldStatus = $order->order_status;
            $order->update(['order_status' => $newStatus]);

            Log::info("[Milestone] Order {$order->order_number} transitioned: {$oldStatus} -> {$newStatus}");

            // Fire notifications for specific transitions
            if ($newStatus === IorForeignOrder::STATUS_DELIVERED) {
                $this->notifier->sendDelivered($order);
            } elseif ($newStatus === IorForeignOrder::STATUS_SHIPPED && $oldStatus !== IorForeignOrder::STATUS_SHIPPED) {
                $this->notifier->sendShipped($order);
            }
        }
    }

    /**
     * Map provider-specific status strings to internal IorForeignOrder constants.
     */
    private function mapCourierStatus(string $status, string $provider): ?string
    {
        $status = strtolower($status);

        return match($provider) {
            'pathao' => match($status) {
                'delivered'         => IorForeignOrder::STATUS_DELIVERED,
                'picked_up'         => IorForeignOrder::STATUS_SHIPPED,
                'returned_to_hub'   => IorForeignOrder::STATUS_CUSTOMS, // Pathao hub can act as custom hold
                'cancelled'         => IorForeignOrder::STATUS_CANCELLED,
                default             => null,
            },
            'steadfast' => match($status) {
                'delivered'         => IorForeignOrder::STATUS_DELIVERED,
                'cancelled'         => IorForeignOrder::STATUS_CANCELLED,
                'in_transit'        => IorForeignOrder::STATUS_SHIPPED,
                default             => null,
            },
            'redx' => match($status) {
                'delivered'         => IorForeignOrder::STATUS_DELIVERED,
                'delivery_failed'   => null, // Keep status as is for retry
                'cancelled'         => IorForeignOrder::STATUS_CANCELLED,
                default             => null,
            },
            default => null,
        };
    }
}
