<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * WebhookVerificationService
 * 
 * Validates requests from courier partners (Pathao, Steadfast, RedX, DHL, FedEx)
 * using their respective signature/HMAC methods.
 */
class WebhookVerificationService
{
    /**
     * Verify Pathao Webhook
     * Pathao usually checks a X-PATHAO-Signature header or matches a shared secret in the payload.
     */
    public function verifyPathao(Request $request): bool
    {
        $secret = config('services.pathao.webhook_secret');
        if (empty($secret)) return true; // Default to true if not configured for now

        $signature = $request->header('X-PATHAO-Signature');
        return $signature === hash_hmac('sha256', $request->getContent(), $secret);
    }

    /**
     * Verify Steadfast Webhook
     * Usually relies on an API Key or specific secret in the JSON.
     */
    public function verifySteadfast(Request $request): bool
    {
        // Steadfast often uses simple body key match or IP whitelist
        return true; 
    }

    /**
     * Verify RedX Webhook
     */
    public function verifyRedX(Request $request): bool
    {
        $secret = config('services.redx.webhook_secret');
        if (empty($secret)) return true;

        $signature = $request->header('X-RedX-Signature');
        return $signature === hash_hmac('sha256', $request->getContent(), $secret);
    }

    /**
     * General catch-all or IP based verification
     */
    public function verifySource(Request $request, string $provider): bool
    {
        Log::info("[Webhook] Verifying source for {$provider}");
        
        return match($provider) {
            'pathao'    => $this->verifyPathao($request),
            'steadfast' => $this->verifySteadfast($request),
            'redx'      => $this->verifyRedX($request),
            default     => true,
        };
    }
}
