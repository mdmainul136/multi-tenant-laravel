<?php

namespace App\Services\Payment\Gateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BNPL Gateway — Tabby, Tamara, Postpay.
 *
 * Business model:
 *   - Merchant gets paid IMMEDIATELY (minus ~3-5% BNPL fee)
 *   - Customer pays in 4 splits (Tabby) or 3 splits (Tamara)
 *   - BNPL provider takes the credit risk
 *
 * Refund → adjusts remaining installments (not bank refund).
 */
class BNPLGateway
{
    // ── Provider selection ─────────────────────────────────────────────────

    /**
     * Check eligibility for all configured BNPL providers.
     * Show option to user only if eligible.
     */
    public function checkEligibility(string $provider, float $amount, string $currency, array $customer): array
    {
        return match (strtolower($provider)) {
            'tabby'   => $this->tabbyEligibility($amount, $currency, $customer),
            'tamara'  => $this->tamaraEligibility($amount, $currency, $customer),
            'postpay' => $this->postpayEligibility($amount, $currency, $customer),
            default   => ['success' => false, 'message' => "Unknown BNPL provider: {$provider}"],
        };
    }

    /**
     * Initiate checkout session for given BNPL provider.
     */
    public function initiate(string $provider, array $params): array
    {
        return match (strtolower($provider)) {
            'tabby'   => $this->tabbyInitiate($params),
            'tamara'  => $this->tamaraInitiate($params),
            'postpay' => $this->postpayInitiate($params),
            default   => ['success' => false, 'message' => "Unknown provider: {$provider}"],
        };
    }

    /**
     * Refund — adjusts remaining installments via BNPL provider API.
     * Customer does NOT receive a bank refund — future installments are reduced/cancelled.
     */
    public function refund(string $provider, string $externalOrderId, float $amount, string $reason = ''): array
    {
        return match (strtolower($provider)) {
            'tabby'   => $this->tabbyRefund($externalOrderId, $amount),
            'tamara'  => $this->tamaraRefund($externalOrderId, $amount),
            'postpay' => ['success' => false, 'message' => 'Postpay refund: contact support'],
            default   => ['success' => false, 'message' => 'Unknown provider'],
        };
    }

    // ── Tabby ──────────────────────────────────────────────────────────────

    protected function tabbyEligibility(float $amount, string $currency, array $customer): array
    {
        $apiKey  = config('services.tabby.public_key');
        $baseUrl = config('services.tabby.sandbox', true)
            ? 'https://api.tabby.ai/api/v2'
            : 'https://api.tabby.ai/api/v2';

        $response = Http::withToken($apiKey)
            ->post("{$baseUrl}/checkout", [
                'payment' => [
                    'amount'      => (string) $amount,
                    'currency'    => $currency,
                    'description' => 'BNPL eligibility check',
                    'buyer'       => [
                        'name'  => $customer['name'] ?? 'Customer',
                        'email' => $customer['email'] ?? '',
                        'phone' => $customer['phone'] ?? '',
                    ],
                    'order'       => ['tax_amount' => '0', 'shipping_amount' => '0', 'discount_amount' => '0', 'items' => []],
                    'order_history'=> [],
                ],
                'lang'             => 'en',
                'merchant_code'    => config('services.tabby.merchant_code'),
                'merchant_urls'    => [
                    'success' => route('tabby.success'),
                    'cancel'  => route('tabby.cancel'),
                    'failure' => route('tabby.failure'),
                ],
            ]);

        $data = $response->json();
        $available = isset($data['configuration']['available_products']['installments']);

        return [
            'success'    => $available,
            'eligible'   => $available,
            'session_id' => $data['id'] ?? null,
            'raw'        => $data,
        ];
    }

    protected function tabbyInitiate(array $params): array
    {
        // Reuse the session from eligibility check (recommended flow)
        if (!empty($params['session_id'])) {
            $checkoutUrl = "https://checkout.tabby.ai/?sessionId={$params['session_id']}";
            return ['success' => true, 'redirect_url' => $checkoutUrl, 'provider' => 'tabby'];
        }

        // Or re-create session (less optimal)
        return $this->tabbyEligibility($params['amount'], $params['currency'], $params['customer'] ?? []);
    }

    protected function tabbyRefund(string $paymentId, float $amount): array
    {
        $apiKey = config('services.tabby.secret_key');
        $response = Http::withToken($apiKey)
            ->post("https://api.tabby.ai/api/v2/payments/{$paymentId}/captures/{$paymentId}/refunds", [
                'amount' => (string) $amount,
            ]);

        $data = $response->json();
        $success = $response->successful();

        Log::info("Tabby refund: payment={$paymentId} amount={$amount} success={$success}");

        return [
            'success'     => $success,
            'refund_id'   => $data['id'] ?? null,
            'note'        => 'Remaining installments adjusted',
            'raw'         => $data,
        ];
    }

    // ── Tamara ─────────────────────────────────────────────────────────────

    protected function tamaraEligibility(float $amount, string $currency, array $customer): array
    {
        $apiKey  = config('services.tamara.api_token');
        $baseUrl = config('services.tamara.sandbox', true)
            ? 'https://api-sandbox.tamara.co'
            : 'https://api.tamara.co';

        $response = Http::withToken($apiKey)
            ->post("{$baseUrl}/checkout", [
                'order_reference_id' => 'ELIG-' . time(),
                'total_amount'       => ['amount' => $amount, 'currency' => $currency],
                'description'        => 'Tamara eligibility',
                'country_code'       => $currency === 'SAR' ? 'SA' : 'AE',
                'payment_type'       => 'PAY_BY_INSTALMENTS',
                'instalments'        => 3, // Tamara = 3 splits
                'consumer'           => [
                    'first_name'  => $customer['name'] ?? 'Customer',
                    'email'       => $customer['email'] ?? '',
                    'phone_number'=> $customer['phone'] ?? '',
                ],
                'merchant_url'       => [
                    'success'       => route('tamara.success'),
                    'failure'       => route('tamara.failure'),
                    'cancel'        => route('tamara.cancel'),
                    'notification'  => route('tamara.notify'),
                ],
                'items'              => [],
                'discount'           => ['amount' => '0', 'currency' => $currency],
                'tax_amount'         => ['amount' => '0', 'currency' => $currency],
                'shipping_amount'    => ['amount' => '0', 'currency' => $currency],
            ]);

        $data    = $response->json();
        $success = $response->successful() && !empty($data['checkout_id']);

        return [
            'success'      => $success,
            'eligible'     => $success,
            'checkout_id'  => $data['checkout_id'] ?? null,
            'checkout_url' => $data['checkout_url'] ?? null,
            'raw'          => $data,
        ];
    }

    protected function tamaraInitiate(array $params): array
    {
        $result = $this->tamaraEligibility($params['amount'], $params['currency'], $params['customer'] ?? []);
        if ($result['success']) {
            return array_merge($result, ['provider' => 'tamara', 'redirect_url' => $result['checkout_url']]);
        }
        return $result;
    }

    protected function tamaraRefund(string $orderId, float $amount): array
    {
        $apiKey  = config('services.tamara.api_token');
        $baseUrl = config('services.tamara.sandbox', true)
            ? 'https://api-sandbox.tamara.co'
            : 'https://api.tamara.co';

        $response = Http::withToken($apiKey)
            ->post("{$baseUrl}/orders/{$orderId}/refund", [
                'total_amount'   => ['amount' => $amount, 'currency' => 'SAR'],
                'comment'        => 'Customer refund',
                'refund_items'   => [],
                'refund_img_url' => '',
            ]);

        return [
            'success' => $response->successful(),
            'note'    => 'Remaining installments adjusted by Tamara',
            'raw'     => $response->json(),
        ];
    }

    // ── Postpay ────────────────────────────────────────────────────────────

    protected function postpayEligibility(float $amount, string $currency, array $customer): array
    {
        // Postpay is UAE-focused; basic eligibility by amount range
        $eligible = $amount >= 200 && $amount <= 5000;
        return ['success' => true, 'eligible' => $eligible, 'provider' => 'postpay'];
    }

    protected function postpayInitiate(array $params): array
    {
        $apiKey  = config('services.postpay.api_key');
        $baseUrl = config('services.postpay.sandbox', true)
            ? 'https://sandbox.postpay.io'
            : 'https://api.postpay.io';

        $response = Http::withBasicAuth($apiKey, '')
            ->post("{$baseUrl}/v1/checkout", [
                'total_amount' => (int)($params['amount'] * 100), // fils
                'currency'     => $params['currency'] ?? 'AED',
                'reference'    => $params['reference'] ?? uniqid('PP-'),
                'successful_callback_url' => route('postpay.success'),
                'cancelled_callback_url'  => route('postpay.cancel'),
                'order'        => ['line_items' => []],
                'consumer'     => ['first_name' => $params['customer']['name'] ?? 'Customer'],
            ]);

        $data = $response->json();

        return [
            'success'      => $response->successful(),
            'redirect_url' => $data['redirect_url'] ?? null,
            'token'        => $data['token'] ?? null,
            'provider'     => 'postpay',
            'raw'          => $data,
        ];
    }
}
