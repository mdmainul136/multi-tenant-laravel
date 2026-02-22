<?php

namespace App\Modules\Ecommerce\Services;

use App\Models\Ecommerce\Refund;
use App\Models\Ecommerce\Order;
use Illuminate\Support\Facades\DB;

class RefundService
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Create a refund request.
     */
    public function requestRefund(array $data): Refund
    {
        return Refund::create([
            'order_id' => $data['order_id'],
            'customer_id' => $data['customer_id'],
            'amount' => $data['amount'],
            'reason' => $data['reason'],
            'refund_method' => $data['refund_method'] ?? 'wallet',
            'status' => 'pending'
        ]);
    }

    /**
     * Approve and process a refund.
     */
    public function approveRefund(int $refundId, string $adminNote = null): Refund
    {
        return DB::transaction(function () use ($refundId, $adminNote) {
            $refund = Refund::findOrFail($refundId);
            
            if ($refund->status !== 'pending') {
                throw new \Exception("Refund has already been processed.");
            }

            if ($refund->refund_method === 'wallet') {
                $this->walletService->refund(
                    $refund->customer_id, 
                    $refund->amount, 
                    "Refund approved for Order #{$refund->order->order_number}",
                    $refund->id
                );
            }

            $refund->update([
                'status' => 'approved',
                'admin_note' => $adminNote,
                'processed_at' => now()
            ]);

            // Update order status if needed
            $refund->order->update(['status' => 'refunded']);

            return $refund;
        });
    }

    /**
     * Reject a refund request.
     */
    public function rejectRefund(int $refundId, string $adminNote): Refund
    {
        $refund = Refund::findOrFail($refundId);
        $refund->update([
            'status' => 'rejected',
            'admin_note' => $adminNote,
            'processed_at' => now()
        ]);

        return $refund;
    }
}
