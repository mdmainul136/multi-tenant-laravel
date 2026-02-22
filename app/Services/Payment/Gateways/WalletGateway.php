<?php

namespace App\Services\Payment\Gateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * STC Pay Wallet Gateway (Saudi Arabia).
 *
 * Flow:
 *   1. merchant calls initiate() → gets OTP request sent to user's phone
 *   2. User enters OTP
 *   3. merchant calls confirm() → payment completed
 *   4. Refund goes back to wallet (instant/24h)
 *
 * Docs: https://b2b.stcpay.com.sa/docs
 */
class WalletGateway
{
    protected string $merchantId;
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->merchantId = config('services.stc_pay.merchant_id');
        $this->apiKey     = config('services.stc_pay.api_key');
        $this->baseUrl    = config('services.stc_pay.sandbox', true)
            ? 'https://sandbox.stcpay.com.sa/b2b/payment'
            : 'https://b2b.stcpay.com.sa/b2b/payment';
    }

    // ── Initiate (sends OTP to user's STC Pay registered phone) ───────────

    /**
     * Step 1: Initiate STC Pay payment.
     * STC Pay sends OTP to user's registered mobile.
     */
    public function initiate(string $mobile, float $amount, string $reference, string $description = ''): array
    {
        $response = Http::withHeaders([
            'apikey'      => $this->apiKey,
            'MerchantID'  => $this->merchantId,
            'Content-Type'=> 'application/json',
        ])->post("{$this->baseUrl}/1.0/DirectPayment/initiation", [
            'MobileNo'    => $mobile,    // '05xxxxxxxx'
            'Amount'      => $amount,
            'MerchantID'  => $this->merchantId,
            'RefNum'      => $reference,
            'Remarks'     => $description,
            'NotifyURL'   => route('stcpay.callback'),
        ]);

        $data = $response->json();

        if (!$response->successful() || ($data['Code'] ?? null) !== '001') {
            Log::error('STC Pay initiation failed', $data);
            return [
                'success' => false,
                'message' => $data['Description'] ?? 'STC Pay initiation failed',
                'raw'     => $data,
            ];
        }

        return [
            'success'     => true,
            'otp_ref'     => $data['PaymentReference'],  // Needed for confirmation step
            'message'     => 'OTP sent to customer mobile',
            'expires_in'  => 300, // 5 minutes OTP validity
            'raw'         => $data,
        ];
    }

    /**
     * Step 2: Confirm payment with OTP entered by user.
     */
    public function confirm(string $paymentReference, string $otp, float $amount): array
    {
        $response = Http::withHeaders([
            'apikey'      => $this->apiKey,
            'MerchantID'  => $this->merchantId,
            'Content-Type'=> 'application/json',
        ])->post("{$this->baseUrl}/1.0/DirectPayment/confirmation", [
            'MerchantID'       => $this->merchantId,
            'Amount'           => $amount,
            'PaymentReference' => $paymentReference,
            'OTPValue'         => $otp,
        ]);

        $data = $response->json();

        if (!$response->successful() || ($data['Code'] ?? null) !== '001') {
            Log::error('STC Pay confirmation failed', $data);
            return [
                'success' => false,
                'message' => $data['Description'] ?? 'STC Pay OTP confirmation failed',
                'raw'     => $data,
            ];
        }

        Log::info("STC Pay payment confirmed: ref={$paymentReference} amount={$amount}");

        return [
            'success'      => true,
            'transaction_id'=> $data['TransactionID'] ?? null,
            'status'       => 'paid',
            'raw'          => $data,
        ];
    }

    // ── Refund (goes back to STC Pay wallet — instant/24h) ─────────────────

    /**
     * Refund to STC Pay wallet.
     * Much faster than card refunds (instant or within 24 hours).
     */
    public function refund(string $transactionId, float $amount, string $reason = ''): array
    {
        $response = Http::withHeaders([
            'apikey'      => $this->apiKey,
            'MerchantID'  => $this->merchantId,
            'Content-Type'=> 'application/json',
        ])->post("{$this->baseUrl}/1.0/Refund", [
            'MerchantID'    => $this->merchantId,
            'Amount'        => $amount,
            'TransactionID' => $transactionId,
            'Remarks'       => $reason,
        ]);

        $data = $response->json();

        if (!$response->successful()) {
            Log::error("STC Pay refund failed: txn={$transactionId}", $data);
            return ['success' => false, 'message' => $data['Description'] ?? 'Refund failed'];
        }

        return [
            'success'    => true,
            'refund_id'  => $data['RefundID'] ?? null,
            'eta'        => 'instant_to_24h',   // Much better than card!
            'destination'=> 'stc_pay_wallet',
            'raw'        => $data,
        ];
    }

    /**
     * Check wallet balance (used before showing STC Pay option).
     * Some integrations allow pre-checking if user has sufficient balance.
     */
    public function checkBalance(string $mobile): array
    {
        // Note: Balance check requires customer's consent / SSO token.
        // In practice, show STC Pay option always and handle insufficient_funds error.
        return ['success' => true, 'available' => true];
    }
}
