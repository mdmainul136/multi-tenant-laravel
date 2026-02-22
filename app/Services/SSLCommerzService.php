<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SSLCommerz Payment Gateway Service
 * Bangladesh-local payment gateway (bKash, Nagad, Rocket, cards via SSLCommerz).
 *
 * Sandbox docs: https://developer.sslcommerz.com/doc/v4/
 */
class SSLCommerzService
{
    protected string $storeId;
    protected string $storePassword;
    protected bool   $sandbox;
    protected string $baseUrl;

    public function __construct()
    {
        $this->storeId       = config('services.sslcommerz.store_id');
        $this->storePassword = config('services.sslcommerz.store_password');
        $this->sandbox       = config('services.sslcommerz.sandbox', true);

        $this->baseUrl = $this->sandbox
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
    }

    /**
     * Initiate a payment session.
     * Returns redirect URL to SSLCommerz payment page.
     */
    public function initiatePayment(array $params): array
    {
        $payload = array_merge([
            'store_id'         => $this->storeId,
            'store_passwd'     => $this->storePassword,
            'currency'         => 'BDT',
            'success_url'      => route('sslcommerz.success'),
            'fail_url'         => route('sslcommerz.fail'),
            'cancel_url'       => route('sslcommerz.cancel'),
            'ipn_url'          => route('sslcommerz.ipn'),
            'emi_option'       => 0,
            'cus_add1'         => 'N/A',
            'cus_city'         => 'Dhaka',
            'cus_country'      => 'Bangladesh',
            'shipping_method'  => 'NO',
            'product_name'     => $params['product_name'] ?? 'Module Subscription',
            'product_category' => 'SaaS',
            'product_profile'  => 'general',
        ], $params);

        $response = Http::asForm()->post(
            $this->baseUrl . '/gwprocess/v4/api.php',
            $payload
        );

        if ($response->failed()) {
            Log::error('SSLCommerz initiate failed', ['status' => $response->status()]);
            throw new \Exception('SSLCommerz initiation failed: ' . $response->body());
        }

        $data = $response->json();

        if (($data['status'] ?? '') !== 'SUCCESS') {
            Log::error('SSLCommerz initiate error', $data);
            throw new \Exception($data['failedreason'] ?? 'SSLCommerz initiation failed');
        }

        Log::info('SSLCommerz session initiated', ['sessionkey' => $data['sessionkey'] ?? null]);

        return [
            'success'     => true,
            'sessionkey'  => $data['sessionkey'],
            'redirect_url'=> $data['GatewayPageURL'],
            'raw'         => $data,
        ];
    }

    /**
     * Validate IPN (Instant Payment Notification) from SSLCommerz.
     * Call after redirect callback to confirm authenticity.
     */
    public function validatePayment(string $valId): array
    {
        $response = Http::get($this->baseUrl . '/validator/api/validationserverAPI.php', [
            'val_id'       => $valId,
            'store_id'     => $this->storeId,
            'store_passwd'  => $this->storePassword,
            'format'       => 'json',
        ]);

        if ($response->failed()) {
            throw new \Exception('SSLCommerz validation request failed');
        }

        $data = $response->json();

        $validStatuses = ['VALID', 'VALIDATED'];
        if (!in_array($data['status'] ?? '', $validStatuses)) {
            Log::warning('SSLCommerz payment NOT valid', ['val_id' => $valId, 'status' => $data['status'] ?? null]);
            return ['success' => false, 'message' => 'Payment validation failed', 'data' => $data];
        }

        Log::info('SSLCommerz payment validated', ['val_id' => $valId, 'amount' => $data['currency_amount'] ?? null]);

        return ['success' => true, 'data' => $data];
    }

    /**
     * Verify IPN hash for security (prevents fake IPN requests).
     */
    public function verifyIpnHash(array $postData): bool
    {
        if (empty($postData['verify_sign']) || empty($postData['verify_key'])) {
            return false;
        }

        $keys  = explode(',', $postData['verify_key']);
        $parts = [];

        foreach ($keys as $key) {
            $parts[] = $key . '=' . ($postData[$key] ?? '');
        }

        $parts[] = 'store_passwd=' . md5($this->storePassword);
        sort($parts);

        $hashString = implode('&', $parts);
        $hash       = md5($hashString);

        return $hash === $postData['verify_sign'];
    }
}
