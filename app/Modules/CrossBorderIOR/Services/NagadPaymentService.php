<?php

namespace App\Modules\CrossBorderIOR\Services;

use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Models\CrossBorderIOR\IorPaymentTransaction;
use App\Models\CrossBorderIOR\IorSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NagadPaymentService
 *
 * Nagad MFS payment gateway integration for IOR orders.
 * Nagad uses a 2-step flow:
 *   1. Initialize  → POST /check-out/initialize/{merchantId}/{orderId}
 *   2. Complete    → POST /check-out/complete/{paymentRefId}
 *
 * Nagad API docs: https://nagad.com.bd/wp-content/uploads/2022/06/Nagad-PGW-Integration-Manual-V4.0.pdf
 *
 * NOTE: Production RSA key signing is handled here using openssl_private_encrypt.
 * Store your RSA private key (PEM) in ior_settings as `nagad_private_key`.
 */
class NagadPaymentService
{
    // Nagad API endpoints
    private const SANDBOX_URL    = 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0';
    private const PRODUCTION_URL = 'https://api.mynagad.com/api/dfs';

    private string $merchantId;
    private string $merchantKey;          // Nagad-issued public key (for encrypting data TO Nagad)
    private string $merchantPrivateKey;   // Your RSA private key (for signing requests)
    private string $baseUrl;
    private bool   $sandbox;

    // ──────────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->merchantId         = IorSetting::get('nagad_merchant_id', '');
        $this->merchantKey        = IorSetting::get('nagad_merchant_key', '');
        $this->merchantPrivateKey = IorSetting::get('nagad_private_key', '');
        $this->sandbox            = (bool) IorSetting::get('nagad_sandbox', true);
        $this->baseUrl            = $this->sandbox ? self::SANDBOX_URL : self::PRODUCTION_URL;
    }

    // ──────────────────────────────────────────────────────────────
    // PUBLIC
    // ──────────────────────────────────────────────────────────────

    public function isConfigured(): bool
    {
        return !empty($this->merchantId) && !empty($this->merchantKey);
    }

    /**
     * Step 1: Initialize a Nagad payment session.
     *
     * @return array ['redirect_url' => string, 'payment_ref_id' => string, 'transaction_id' => string]
     */
    public function initiate(IorForeignOrder $order, float $amount, string $paymentType = 'advance'): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Nagad not configured. Set nagad_merchant_id and nagad_merchant_key in IOR Settings.');
        }

        $transactionId = 'NAGAD-' . $order->order_number . '-' . now()->format('YmdHis');
        $dateTime      = now()->format('YmdHis');

        // Build sensitive data (will be encrypted with Nagad public key)
        $sensitiveData = [
            'merchantId' => $this->merchantId,
            'datetime'   => $dateTime,
            'orderId'    => $transactionId,
            'challenge'  => bin2hex(random_bytes(8)),
        ];

        $sensitiveDataEncrypted = $this->encryptWithNagadPublicKey(json_encode($sensitiveData));
        $signature              = $this->signWithPrivateKey(json_encode($sensitiveData));

        // Call Nagad Initialize endpoint
        $url = "{$this->baseUrl}/check-out/initialize/{$this->merchantId}/{$transactionId}";

        $response = Http::withHeaders([
            'X-KM-Api-Version' => 'v-0.2.0',
            'X-KM-IP-V4'       => request()->ip() ?? '127.0.0.1',
            'X-KM-Client-Type' => 'PC_WEB',
            'Content-Type'     => 'application/json',
        ])->timeout(30)->post($url, [
            'accountNumber' => $this->merchantId,
            'dateTime'      => $dateTime,
            'sensitiveData' => $sensitiveDataEncrypted,
            'signature'     => $signature,
        ]);

        $result = $response->json();

        Log::info('[IOR Nagad] Init response', ['status' => $response->status(), 'body' => $result]);

        if ($response->failed() || empty($result['sensitiveData'])) {
            throw new \RuntimeException($result['message'] ?? 'Nagad initialization failed. Status: ' . $response->status());
        }

        // Decrypt response sensitiveData
        $responseData = json_decode($this->decryptWithPrivateKey($result['sensitiveData'] ?? ''), true) ?? [];
        $paymentRefId = $responseData['paymentReferenceId'] ?? $result['paymentReferenceId'] ?? null;
        $challenge    = $responseData['challenge'] ?? '';

        if (!$paymentRefId) {
            throw new \RuntimeException('Nagad did not return a paymentReferenceId.');
        }

        // Step 2: Complete (send amount + product info)
        $completeData = [
            'merchantId'     => $this->merchantId,
            'orderId'        => $transactionId,
            'currencyCode'   => '050',  // BDT
            'amount'         => number_format($amount, 2, '.', ''),
            'challenge'      => $challenge,
        ];

        $completeEncrypted = $this->encryptWithNagadPublicKey(json_encode($completeData));
        $completeSignature = $this->signWithPrivateKey(json_encode($completeData));

        $completeUrl = "{$this->baseUrl}/check-out/complete/{$paymentRefId}";

        $completeResp = Http::withHeaders([
            'X-KM-Api-Version' => 'v-0.2.0',
            'X-KM-IP-V4'       => request()->ip() ?? '127.0.0.1',
            'X-KM-Client-Type' => 'PC_WEB',
            'Content-Type'     => 'application/json',
        ])->timeout(30)->post($completeUrl, [
            'sensitiveData' => $completeEncrypted,
            'signature'     => $completeSignature,
            'merchantCallbackURL' => url("/api/ior/payment/nagad/callback?order_id={$order->id}&type={$paymentType}"),
        ]);

        $completeResult = $completeResp->json();

        Log::info('[IOR Nagad] Complete response', ['status' => $completeResp->status(), 'body' => $completeResult]);

        if ($completeResp->failed()) {
            throw new \RuntimeException($completeResult['message'] ?? 'Nagad complete step failed.');
        }

        $redirectUrl = $completeResult['callBackUrl'] ?? null;

        if (!$redirectUrl) {
            throw new \RuntimeException('Nagad did not return a redirect URL from complete step.');
        }

        // Store pending transaction
        IorPaymentTransaction::create([
            'order_id'         => $order->id,
            'transaction_id'   => $transactionId,
            'gateway'          => 'nagad',
            'payment_type'     => $paymentType,
            'amount'           => $amount,
            'currency'         => 'BDT',
            'status'           => 'initiated',
            'gateway_response' => [
                'payment_ref_id' => $paymentRefId,
                'sensitive_data' => $sensitiveData,
                'timestamp'      => now()->toIso8601String(),
            ],
        ]);

        // Log to timeline
        \DB::table('ior_logs')->insert([
            'order_id' => $order->id,
            'event'    => 'payment_initiated',
            'payload'  => json_encode(['gateway' => 'nagad', 'amount' => $amount, 'payment_type' => $paymentType]),
            'status'   => 'ok',
            'visible_to_customer' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'redirect_url'    => $redirectUrl,
            'payment_ref_id'  => $paymentRefId,
            'transaction_id'  => $transactionId,
            'amount'          => $amount,
        ];
    }

    /**
     * Verify a Nagad callback and mark order as paid.
     * Called from PaymentController::nagadCallback().
     */
    public function verify(string $paymentRefId, IorForeignOrder $order, string $paymentType = 'advance'): array
    {
        $verifyUrl = "{$this->baseUrl}/verify/payment/{$paymentRefId}";

        $response = Http::withHeaders([
            'X-KM-Api-Version' => 'v-0.2.0',
            'Content-Type'     => 'application/json',
        ])->timeout(20)->get($verifyUrl);

        $result = $response->json();

        Log::info('[IOR Nagad] Verify response', $result);

        if ($response->failed() || ($result['status'] ?? '') !== 'Success') {
            // Log failure to timeline
            \DB::table('ior_logs')->insert([
                'order_id' => $order->id,
                'event'    => 'payment_failed',
                'payload'  => json_encode(['gateway' => 'nagad', 'error' => $result['message'] ?? 'Verification failed']),
                'status'   => 'error',
                'visible_to_customer' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return ['success' => false, 'message' => $result['message'] ?? 'Nagad verification failed.'];
        }

        $amount = (float) ($result['amount'] ?? 0);

        // Record transaction
        IorPaymentTransaction::updateOrCreate(
            ['transaction_id' => $result['merchantInvoiceNumber'] ?? $paymentRefId, 'gateway' => 'nagad'],
            [
                'order_id'           => $order->id,
                'gateway'            => 'nagad',
                'payment_type'       => $paymentType,
                'amount'             => $amount,
                'currency'           => 'BDT',
                'status'             => 'paid',
                'bank_transaction_id'=> $result['issuerPaymentRefNo'] ?? null,
                'gateway_response'   => $result,
            ]
        );

        // Log success to timeline
        \DB::table('ior_logs')->insert([
            'order_id' => $order->id,
            'event'    => 'payment_confirmed',
            'payload'  => json_encode(['gateway' => 'nagad', 'amount' => $amount, 'payment_type' => $paymentType]),
            'status'   => 'ok',
            'visible_to_customer' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update order payment status
        $updateFields = [];
        if ($paymentType === 'advance') {
            $updateFields['advance_paid']   = true;
            $updateFields['order_status']   = IorForeignOrder::STATUS_SOURCING;
            $updateFields['payment_status'] = $order->remaining_paid ? 'paid' : 'partial';
        } elseif ($paymentType === 'remaining') {
            $updateFields['remaining_paid'] = true;
            $updateFields['payment_status'] = 'paid';
        }

        if (!empty($updateFields)) {
            $order->update($updateFields);
        }

        return ['success' => true, 'amount' => $amount, 'data' => $result];
    }

    // ──────────────────────────────────────────────────────────────
    // CRYPTO HELPERS
    // ──────────────────────────────────────────────────────────────

    /**
     * Encrypt data with Nagad's PGW public key (RSA PKCS#1 OAEP or PKCS#1 v1.5).
     * Nagad provides their public key as part of merchant onboarding.
     */
    private function encryptWithNagadPublicKey(string $plainText): string
    {
        if (empty($this->merchantKey)) {
            // Fallback: base64 (sandbox / unconfigured)
            return base64_encode($plainText);
        }

        $pubKeyPem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split($this->merchantKey, 64, "\n")
            . "-----END PUBLIC KEY-----";

        $publicKey = openssl_pkey_get_public($pubKeyPem);

        if (!$publicKey) {
            Log::warning('[IOR Nagad] Could not load Nagad public key, using base64 fallback');
            return base64_encode($plainText);
        }

        $encrypted = '';
        openssl_public_encrypt($plainText, $encrypted, $publicKey, OPENSSL_PKCS1_OAEP_PADDING);

        return base64_encode($encrypted);
    }

    /**
     * Sign data with merchant's RSA private key (PKCS#1 SHA-256).
     */
    private function signWithPrivateKey(string $data): string
    {
        if (empty($this->merchantPrivateKey)) {
            // Fallback for sandbox/unconfigured — HMAC-SHA256 with merchant key
            return base64_encode(hash_hmac('sha256', $data, $this->merchantKey ?? 'dev', true));
        }

        $privKeyPem = "-----BEGIN RSA PRIVATE KEY-----\n"
            . chunk_split($this->merchantPrivateKey, 64, "\n")
            . "-----END RSA PRIVATE KEY-----";

        $privateKey = openssl_pkey_get_private($privKeyPem);

        if (!$privateKey) {
            Log::warning('[IOR Nagad] Could not load private key, using HMAC fallback');
            return base64_encode(hash_hmac('sha256', $data, $this->merchantKey ?? 'dev', true));
        }

        $signature = '';
        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    /**
     * Decrypt Nagad's encrypted response using our private key.
     */
    private function decryptWithPrivateKey(string $cipherBase64): string
    {
        if (empty($this->merchantPrivateKey)) {
            return base64_decode($cipherBase64);
        }

        $privKeyPem = "-----BEGIN RSA PRIVATE KEY-----\n"
            . chunk_split($this->merchantPrivateKey, 64, "\n")
            . "-----END RSA PRIVATE KEY-----";

        $privateKey = openssl_pkey_get_private($privKeyPem);
        $decrypted  = '';

        if (!$privateKey) {
            return base64_decode($cipherBase64);
        }

        openssl_private_decrypt(base64_decode($cipherBase64), $decrypted, $privateKey, OPENSSL_PKCS1_OAEP_PADDING);

        return $decrypted ?: base64_decode($cipherBase64);
    }
}



