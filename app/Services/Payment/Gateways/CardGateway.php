<?php

namespace App\Services\Payment\Gateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Moyasar Card Gateway — supports MADA, Visa, Mastercard, Apple Pay, Google Pay.
 * Moyasar is KSA-native and provides MADA support (Stripe does NOT support MADA).
 *
 * Docs: https://docs.moyasar.com/api
 * Sandbox: https://api.moyasar.com (with test keys)
 */
class CardGateway
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.moyasar.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.moyasar.secret_key');
    }

    // ── Charge ─────────────────────────────────────────────────────────────

    /**
     * Charge a tokenised card (MADA/Visa/MC/Apple Pay/Google Pay).
     *
     * @param int    $amount      Amount in SMALLEST currency unit (halalas or fils)
     * @param string $currency    'SAR' | 'AED'
     * @param array  $source      ['type' => 'token' | 'creditcard', 'token' => '...']
     * @param string $description
     * @param array  $metadata
     */
    public function charge(int $amount, string $currency, array $source, string $description = '', array $metadata = []): array
    {
        $response = Http::withBasicAuth($this->apiKey, '')
            ->post("{$this->baseUrl}/payments", [
                'amount'      => $amount,
                'currency'    => $currency,
                'source'      => $source,
                'description' => $description,
                'metadata'    => $metadata,
                'callback_url'=> route('moyasar.callback'),
            ]);

        $data = $response->json();

        if (!$response->successful() || ($data['status'] ?? '') === 'failed') {
            Log::error('Moyasar charge failed', ['response' => $data]);
            return [
                'success' => false,
                'message' => $data['message'] ?? $data['type'] ?? 'Card charge failed',
                'raw'     => $data,
            ];
        }

        Log::info("Moyasar charge initiated: id={$data['id']} status={$data['status']}");

        return [
            'success'      => true,
            'payment_id'   => $data['id'],
            'status'       => $data['status'], // 'initiated' | 'paid' | '3ds_required'
            'redirect_url' => $data['source']['transaction_url'] ?? null, // 3DS redirect
            'amount'       => $data['amount'],
            'currency'     => $data['currency'],
            'raw'          => $data,
        ];
    }

    /**
     * Create a reusable token from card details (for saved cards / auto-renewal).
     */
    public function tokenize(array $cardData): array
    {
        $response = Http::withBasicAuth($this->apiKey, '')
            ->post("{$this->baseUrl}/tokens", $cardData);

        $data = $response->json();

        if (!$response->successful()) {
            return ['success' => false, 'message' => $data['message'] ?? 'Tokenization failed'];
        }

        return ['success' => true, 'token' => $data['id'], 'raw' => $data];
    }

    // ── Refund ─────────────────────────────────────────────────────────────

    /**
     * Refund a payment (full or partial).
     * Card refunds: 7–21 business days to bank account.
     */
    public function refund(string $moyasarPaymentId, int $amount, string $reason = ''): array
    {
        $response = Http::withBasicAuth($this->apiKey, '')
            ->post("{$this->baseUrl}/payments/{$moyasarPaymentId}/refunds", [
                'amount' => $amount,
                'note'   => $reason,
            ]);

        $data = $response->json();

        if (!$response->successful()) {
            Log::error("Moyasar refund failed: payment={$moyasarPaymentId}", $data);
            return ['success' => false, 'message' => $data['message'] ?? 'Refund failed'];
        }

        Log::info("Moyasar refund initiated: refund_id={$data['id']} amount={$amount}");

        return [
            'success'    => true,
            'refund_id'  => $data['id'],
            'status'     => $data['status'],
            'eta_days'   => '7-21', // Always inform user of realistic ETA
            'raw'        => $data,
        ];
    }

    // ── Retrieve ───────────────────────────────────────────────────────────

    public function retrieve(string $paymentId): array
    {
        $response = Http::withBasicAuth($this->apiKey, '')
            ->get("{$this->baseUrl}/payments/{$paymentId}");

        return $response->json();
    }

    // ── Apple Pay Session ──────────────────────────────────────────────────

    /**
     * Validate Apple Pay merchant session (required by Apple Pay JS API).
     */
    public function validateApplePayMerchant(string $validationUrl): array
    {
        $response = Http::withBasicAuth($this->apiKey, '')
            ->post("{$this->baseUrl}/applepay/initiate", [
                'validation_url' => $validationUrl,
            ]);

        return $response->json();
    }

    // ── Webhook helper ─────────────────────────────────────────────────────

    /**
     * Verify Moyasar webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret   = config('services.moyasar.webhook_secret');
        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }
}
