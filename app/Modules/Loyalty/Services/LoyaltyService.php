<?php

namespace App\Modules\Loyalty\Services;

use App\Modules\Loyalty\Models\LoyaltyProgram;
use App\Modules\Loyalty\Models\LoyaltyPoint;
use App\Modules\Loyalty\Models\LoyaltyTier;
use App\Models\Ecommerce\Order;

class LoyaltyService
{
    /**
     * Calculate points earned for an order.
     */
    public function calculatePointsFromOrder(Order $order)
    {
        $tenantId = $order->tenant_id;
        $program = LoyaltyProgram::where('tenant_id', $tenantId)->where('is_active', true)->first();

        if (!$program) {
            return 0;
        }

        $basePoints = $order->total * $program->points_per_currency;

        // Apply tier multipliers if applicable
        $totalCustomerPoints = LoyaltyPoint::where('tenant_id', $tenantId)
            ->where('customer_id', $order->customer_id)
            ->sum('points');

        $activeTier = LoyaltyTier::where('tenant_id', $tenantId)
            ->where('min_points', '<=', $totalCustomerPoints)
            ->orderBy('min_points', 'desc')
            ->first();

        $multiplier = $activeTier ? $activeTier->multiplier : 1.0;
        
        return round($basePoints * $multiplier);
    }

    /**
     * Award points to a customer for an order.
     */
    public function awardPointsForOrder(Order $order)
    {
        $points = $this->calculatePointsFromOrder($order);
        
        if ($points > 0) {
            return LoyaltyPoint::create([
                'tenant_id' => $order->tenant_id,
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'points' => $points,
                'transaction_type' => 'earn',
                'description' => "Points earned from order #{$order->order_number}",
            ]);
        }

        return null;
    }

    /**
     * Spend points for a discount.
     */
    public function spendPoints(string $tenantId, int $customerId, int $points, string $description = 'Points redeemed')
    {
        $program = LoyaltyProgram::where('tenant_id', $tenantId)->where('is_active', true)->first();
        
        if (!$program || $points < $program->min_redemption_points) {
            throw new \Exception("Loyalty program inactive or minimum redemption points not met.");
        }

        $balance = LoyaltyPoint::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->sum('points');

        if ($balance < $points) {
            throw new \Exception("Insufficient point balance.");
        }

        return LoyaltyPoint::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'points' => -$points,
            'transaction_type' => 'redeem',
            'description' => $description,
        ]);
    }
}
