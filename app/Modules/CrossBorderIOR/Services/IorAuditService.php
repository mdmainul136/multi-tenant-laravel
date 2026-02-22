<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class IorAuditService
{
    /**
     * Log a sourcing/compliance event.
     */
    public function log(string $event, int $productId, array $data = []): void
    {
        $userId = auth()->id() ?? 0;
        
        Log::info("[IOR Audit] {$event} | Product ID: {$productId} | User: {$userId}", $data);

        // Optional: Store in a dedicated DB table for persistent auditing
        // DB::table('ior_audit_logs')->insert([...]);
    }
}
