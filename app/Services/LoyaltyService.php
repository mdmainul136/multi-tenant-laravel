<?php

namespace App\Services;

use App\Models\Loyalty\LoyaltyProgram;
use App\Models\Loyalty\LoyaltyPoint;
use App\Models\Loyalty\LoyaltyTier;
use Illuminate\Support\Facades\Log;

class LoyaltyService
{
    /**
     * Award points to a customer based on an order.
     */
    public function awardPointsForOrder($order)
    {
        if (!$order->customer_id) return;

        $program = LoyaltyProgram::where('tenant_id', $order->tenant_id)
            ->where('is_active', true)
            ->first();

        if (!$program) return;

        $points = (int)($order->total * $program->points_per_currency);

        if ($points > 0) {
            LoyaltyPoint::create([
                'tenant_id' => $order->tenant_id,
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'points' => $points,
                'transaction_type' => 'earn',
                'description' => "Earned points from order #{$order->order_number}"
            ]);
        }
    }

    /**
     * Calculate discount based on loyalty points for redemption.
     */
    public function calculateRedemptionValue($tenantId, $points)
    {
        $program = LoyaltyProgram::where('tenant_id', $tenantId)->first();
        if (!$program) return 0;

        return $points * $program->currency_per_point;
    }

    /**
     * Redeem points for a customer.
     */
    public function redeemPoints($tenantId, $customerId, $points, $orderId = null)
    {
        $balance = LoyaltyPoint::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->sum('points');

        if ($balance < $points) {
            throw new \Exception("Insufficient loyalty points balance.");
        }

        return LoyaltyPoint::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'points' => -$points,
            'transaction_type' => 'redeem',
            'description' => $orderId ? "Redeemed points for order #{$orderId}" : "Points redemption"
        ]);
    }

    /**
     * Check and update customer tier based on points.
     */
    public function updateCustomerTier($tenantId, $customerId)
    {
        $totalEarned = LoyaltyPoint::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('transaction_type', 'earn')
            ->sum('points');

        $tier = LoyaltyTier::where('tenant_id', $tenantId)
            ->where('min_points', '<=', $totalEarned)
            ->orderBy('min_points', 'desc')
            ->first();

        if ($tier) {
            // Logic to update customer's current tier field if it exists
            // $customer = CRM\Customer::find($customerId);
            // $customer->update(['loyalty_tier_id' => $tier->id]);
        }
    }
}

