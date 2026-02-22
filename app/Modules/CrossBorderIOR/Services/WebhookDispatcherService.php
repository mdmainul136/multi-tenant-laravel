<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDispatcherService
{
    /**
     * Dispatch an event to all active webhooks for a tenant.
     */
    public function dispatch(int $tenantId, string $event, array $payload): void
    {
        $webhooks = \DB::table('ior_tenant_webhooks')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        foreach ($webhooks as $webhook) {
            $allowedEvents = json_decode($webhook->events, true) ?? [];
            if (!in_array($event, $allowedEvents) && !empty($allowedEvents)) {
                continue;
            }

            try {
                $response = Http::withHeaders([
                    'X-IOR-Webhook-Secret' => $webhook->secret_token,
                    'Content-Type' => 'application/json',
                ])->timeout(5)->post($webhook->endpoint_url, [
                    'event' => $event,
                    'tenant_id' => $tenantId,
                    'timestamp' => now()->toDateTimeString(),
                    'data' => $payload,
                ]);

                if ($response->failed()) {
                    Log::warning("[IOR Webhook] Failed to send to {$webhook->endpoint_url}: " . $response->status());
                }
            } catch (\Exception $e) {
                Log::error("[IOR Webhook] Exception for {$webhook->endpoint_url}: " . $e->getMessage());
            }
        }
    }
}
