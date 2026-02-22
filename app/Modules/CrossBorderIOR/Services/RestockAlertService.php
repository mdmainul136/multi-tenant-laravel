<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RestockAlertService
{
    /**
     * Process a restock event.
     */
    public function handleRestock(int $productId, string $url): void
    {
        Log::info("[IOR Alert] Product restocked: $productId ($url)");

        // 1. Create a system notification or audit log
        DB::table('ior_logs')->insert([
            'tenant_id' => 1, // Default for now, should be dynamic
            'product_id' => $productId,
            'action' => 'RESTOCK_DETECTED',
            'status' => 'info',
            'message' => "The product at $url has been restocked and is now available.",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Here you would trigger Email/SMS/Webhook
        // event(new ProductRestocked($productId));
    }
}
