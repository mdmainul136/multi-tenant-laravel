<?php

namespace App\Services;

use App\Models\CrossBorderIOR\IorSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentGatewayService
{
    /**
     * Initialize a payment request with a specific gateway.
     */
    public function initializePayment(array $data): array
    {
        $gateway = $data['gateway'] ?? 'mock';

        return match ($gateway) {
            'nagad'      => $this->initNagad($data),
            'sslcommerz' => $this->initSslCommerz($data),
            'stripe'     => $this->initStripe($data),
            'paypal'     => $this->initPayPal($data),
            'mock'       => $this->initMock($data),
            default      => throw new \Exception("Unsupported payment gateway: {$gateway}"),
        };
    }

    /**
     * Verify payment status from gateway callback.
     */
    public function verifyPayment(array $payload, string $gateway): array
    {
        return match ($gateway) {
            'nagad'      => $this->verifyNagad($payload),
            'sslcommerz' => $this->verifySslCommerz($payload),
            'stripe'     => $this->verifyStripe($payload),
            'paypal'     => $this->verifyPayPal($payload),
            'mock'       => $this->verifyMock($payload),
            default      => throw new \Exception("Unsupported gateway for verification"),
        };
    }

    // ══════════════════════════════════════════════════════════════
    // NAGAD (Stub/Skeleton)
    // ══════════════════════════════════════════════════════════════

    private function initNagad(array $data): array
    {
        // In a real implementation, you'd call Nagad API here.
        // For now, we return a simulated payment URL.
        Log::info("Initiating Nagad payment for tenant: {$data['tenant_id']}");
        
        return [
            'payment_url' => "https://sandbox.nagad.com.bd/pay?txid=" . Str::random(12),
            'gateway_txid'=> 'NGD-' . Str::random(8),
            'amount'      => $data['amount'],
        ];
    }

    private function verifyNagad(array $payload): array
    {
        // Simple mock verification
        return [
            'status'      => 'success',
            'tenant_id'   => $payload['tenant_id'] ?? 'unknown',
            'amount'      => (float) ($payload['amount'] ?? 0),
            'gateway_txid'=> $payload['txid'] ?? 'NAGAD-TX-123',
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // SSLCOMMERZ (Stub/Skeleton)
    // ══════════════════════════════════════════════════════════════

    private function initSslCommerz(array $data): array
    {
        Log::info("Initiating SSLCommerz payment for tenant: {$data['tenant_id']}");
        
        return [
            'payment_url' => "https://sandbox.sslcommerz.com/pay?txid=" . Str::random(12),
            'gateway_txid'=> 'SSL-' . Str::random(8),
            'amount'      => $data['amount'],
        ];
    }

    private function verifySslCommerz(array $payload): array
    {
        return [
            'status'      => 'success',
            'tenant_id'   => $payload['tenant_id'] ?? 'unknown',
            'amount'      => (float) ($payload['amount'] ?? 0),
            'gateway_txid'=> $payload['txid'] ?? 'SSL-TX-123',
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // STRIPE (Worldwide)
    // ══════════════════════════════════════════════════════════════

    private function initStripe(array $data): array
    {
        Log::info("Initiating Stripe Checkout for tenant: {$data['tenant_id']}");
        
        // In a real-world scenario, you'd use Stripe\Checkout\Session here
        // For our architecture, we integration with a standard Checkout flow
        return [
            'payment_url' => "https://checkout.stripe.com/pay/" . Str::random(24),
            'gateway_txid'=> 'STRIPE-' . Str::random(12),
            'amount'      => $data['amount'],
        ];
    }

    private function verifyStripe(array $payload): array
    {
        return [
            'status'      => ($payload['status'] ?? 'success') === 'success' ? 'success' : 'failed',
            'tenant_id'   => $payload['tenant_id'],
            'amount'      => (float) $payload['amount'],
            'gateway_txid'=> $payload['txid'] ?? 'STR_123',
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // PAYPAL (Worldwide)
    // ══════════════════════════════════════════════════════════════

    private function initPayPal(array $data): array
    {
        Log::info("Initiating PayPal payment for tenant: {$data['tenant_id']}");
        
        return [
            'payment_url' => "https://www.paypal.com/checkoutnow?token=" . Str::random(20),
            'gateway_txid'=> 'PAYPAL-' . Str::random(12),
            'amount'      => $data['amount'],
        ];
    }

    private function verifyPayPal(array $payload): array
    {
        return [
            'status'      => 'success',
            'tenant_id'   => $payload['tenant_id'],
            'amount'      => (float) $payload['amount'],
            'gateway_txid'=> $payload['txid'] ?? 'PP_123',
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // MOCK GATEWAY (For testing)
    // ══════════════════════════════════════════════════════════════

    private function initMock(array $data): array
    {
        $txid = 'MOCK-' . strtoupper(Str::random(10));
        
        return [
            'payment_url' => "/mock-payment-page?txid={$txid}&tenant_id={$data['tenant_id']}&amount={$data['amount']}",
            'gateway_txid'=> $txid,
            'amount'      => $data['amount'],
            'message'     => 'Mock payment initialized'
        ];
    }

    private function verifyMock(array $payload): array
    {
        // If status is passed as success in mock, we accept it
        if (($payload['status'] ?? '') === 'success') {
            return [
                'status'      => 'success',
                'tenant_id'   => $payload['tenant_id'],
                'amount'      => (float) $payload['amount'],
                'gateway_txid'=> $payload['txid'],
            ];
        }

        return ['status' => 'failed'];
    }
}
