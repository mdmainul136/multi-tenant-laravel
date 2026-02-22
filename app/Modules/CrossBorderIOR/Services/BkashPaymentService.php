<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\CrossBorderIOR\IorSetting;
use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Models\CrossBorderIOR\IorPaymentTransaction;

class BkashPaymentService
{
    private string $appKey;
    private string $appSecret;
    private string $username;
    private string $password;
    private bool   $sandbox;
    private string $baseUrl;

    public function __construct()
    {
        $this->appKey   = IorSetting::get('bkash_app_key', '');
        $this->appSecret= IorSetting::get('bkash_app_secret', '');
        $this->username = IorSetting::get('bkash_username', '');
        $this->password = IorSetting::get('bkash_password', '');
        $this->sandbox  = (bool) IorSetting::get('bkash_sandbox', true);

        $this->baseUrl = $this->sandbox
            ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'
            : 'https://tokenized.pay.bka.sh/v1.2.0-beta';
    }

    /**
     * Step 1: Grant token from bKash.
     */
    private function grantToken(): string
    {
        $response = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'username'      => $this->username,
            'password'      => $this->password,
        ])->post($this->baseUrl . '/tokenized/checkout/token/grant', [
            'app_key'    => $this->appKey,
            'app_secret' => $this->appSecret,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('bKash token grant failed: ' . $response->body());
        }

        $data = $response->json();

        if (empty($data['id_token'])) {
            throw new \RuntimeException('bKash: invalid token response');
        }

        return $data['id_token'];
    }

    /**
     * Step 2: Create payment — returns bKash payment URL for redirect.
     */
    public function createPayment(
        IorForeignOrder $order,
        float $amount,
        string $callbackUrl,
        string $paymentType = 'advance'
    ): array {
        $token = $this->grantToken();

        $response = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Authorization' => $token,
            'X-APP-Key'     => $this->appKey,
        ])->post($this->baseUrl . '/tokenized/checkout/create', [
            'mode'              => '0011',
            'payerReference'    => (string) $order->id,
            'callbackURL'       => $callbackUrl,
            'amount'            => number_format($amount, 2, '.', ''),
            'currency'          => 'BDT',
            'intent'            => 'sale',
            'merchantInvoiceNumber' => $order->order_number,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('bKash createPayment failed: ' . $response->body());
        }

        $data = $response->json();

        if (empty($data['paymentID'])) {
            throw new \RuntimeException('bKash: no paymentID returned');
        }

        // Log transaction as initiated
        IorPaymentTransaction::create([
            'order_id'         => $order->id,
            'gateway'          => 'bkash',
            'payment_type'     => $paymentType,
            'amount'           => $amount,
            'currency'         => 'BDT',
            'status'           => 'initiated',
            'bkash_payment_id' => $data['paymentID'],
            'gateway_response' => $data,
        ]);

        return [
            'payment_id'   => $data['paymentID'],
            'redirect_url' => $data['bkashURL'],
            'status'       => $data['statusCode'],
        ];
    }

    /**
     * Step 3: Execute payment after customer completes on bKash page.
     * Called from callback URL.
     */
    public function executePayment(string $paymentId): array
    {
        $token = $this->grantToken();

        $response = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Authorization' => $token,
            'X-APP-Key'     => $this->appKey,
        ])->post($this->baseUrl . '/tokenized/checkout/execute', [
            'paymentID' => $paymentId,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('bKash executePayment failed: ' . $response->body());
        }

        $data = $response->json();

        // Update transaction record
        $tx = IorPaymentTransaction::where('bkash_payment_id', $paymentId)->first();
        if ($tx) {
            $success = ($data['statusCode'] ?? '') === '0000';
            $tx->update([
                'status'       => $success ? 'paid' : 'failed',
                'bkash_trx_id' => $data['trxID'] ?? null,
                'transaction_id'=> $data['trxID'] ?? null,
                'gateway_response' => $data,
            ]);

            // If paid, update order
            if ($success && $tx->order_id) {
                $this->updateOrderPaymentStatus($tx->order_id, $tx->payment_type);
            }
        }

        return $data;
    }

    private function updateOrderPaymentStatus(int $orderId, string $paymentType): void
    {
        $order = IorForeignOrder::find($orderId);
        if (!$order) return;

        if ($paymentType === 'advance') {
            $order->update([
                'advance_paid'   => true,
                'payment_status' => $order->remaining_paid ? 'paid' : 'partial',
                'order_status'   => IorForeignOrder::STATUS_SOURCING,
            ]);
        } elseif ($paymentType === 'remaining') {
            $order->update([
                'remaining_paid' => true,
                'payment_status' => 'paid',
            ]);
        }
    }

    /**
     * Check if bKash is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->appKey) && !empty($this->username);
    }
}



