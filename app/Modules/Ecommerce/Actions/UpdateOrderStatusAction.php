<?php

namespace App\Modules\Ecommerce\Actions;

use App\Models\Ecommerce\Order;
use Illuminate\Support\Facades\DB;

class UpdateOrderStatusAction
{
    public function execute(int $orderId, string $status): Order
    {
        $order = Order::findOrFail($orderId);
        $order->update(['status' => $status]);

        // Log the status change
        DB::table('ior_logs')->insert([
            'order_id' => $order->id,
            'event' => 'status_updated',
            'payload' => json_encode(['status' => $status]),
            'visible_to_customer' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $order->fresh();
    }
}
