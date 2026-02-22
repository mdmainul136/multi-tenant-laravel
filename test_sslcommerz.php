<?php
/**
 * SSLCommerz Sandbox Test Script
 * Run: php artisan tinker --execute="require 'test_sslcommerz.php';"
 * Or:  php test_sslcommerz.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== SSLCommerz Sandbox Test ===\n\n";

// Read config
$storeId   = config('services.sslcommerz.store_id');
$storePass = config('services.sslcommerz.store_password');
$sandbox   = config('services.sslcommerz.sandbox');

echo "Store ID:    {$storeId}\n";
echo "Sandbox:     " . ($sandbox ? 'YES' : 'NO') . "\n\n";

// Build test payload
$tran_id = 'TEST-' . time();
$payload = [
    'store_id'         => $storeId,
    'store_passwd'     => $storePass,
    'total_amount'     => 100,          // BDT 100
    'currency'         => 'BDT',
    'tran_id'          => $tran_id,
    'product_name'     => 'HRM Module (Sandbox Test)',
    'product_category' => 'SaaS',
    'product_profile'  => 'general',
    'cus_name'         => 'Test Tenant',
    'cus_email'        => 'test@example.com',
    'cus_add1'         => 'Dhaka, Bangladesh',
    'cus_city'         => 'Dhaka',
    'cus_country'      => 'Bangladesh',
    'cus_phone'        => '01700000000',
    'shipping_method'  => 'NO',
    'emi_option'       => 0,
    'success_url'      => 'http://localhost:8000/api/payment/sslcommerz/success',
    'fail_url'         => 'http://localhost:8000/api/payment/sslcommerz/fail',
    'cancel_url'       => 'http://localhost:8000/api/payment/sslcommerz/cancel',
    'ipn_url'          => 'http://localhost:8000/api/payment/sslcommerz/ipn',
    'value_a'          => '999',  // fake payment_id
    'value_b'          => '1',    // fake tenant_id
    'value_c'          => '2',    // fake module_id
    'value_d'          => 'monthly',
];

$apiUrl = 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php';
echo "Hitting: {$apiUrl}\n";
echo "Tran ID: {$tran_id}\n\n";

$response = \Illuminate\Support\Facades\Http::asForm()->timeout(20)->post($apiUrl, $payload);

if ($response->failed()) {
    echo "❌ HTTP Request FAILED! Status: " . $response->status() . "\n";
    echo $response->body() . "\n";
    exit(1);
}

$data = $response->json();

echo "Response Status: " . ($data['status'] ?? 'N/A') . "\n\n";

if (($data['status'] ?? '') === 'SUCCESS') {
    echo "✅ SSLCommerz Sandbox: SUCCESS!\n\n";
    echo "Session Key:   " . ($data['sessionkey'] ?? 'N/A') . "\n";
    echo "Redirect URL:  " . ($data['GatewayPageURL'] ?? 'N/A') . "\n\n";
    echo "👆 Open the redirect URL in a browser to test the payment page.\n";
    echo "\nTest Card Details:\n";
    echo "  Card Number : 4111111111111111\n";
    echo "  Exp         : Any future date\n";
    echo "  CVV         : 123\n";
    echo "  OTP         : OTP\n";
} else {
    echo "❌ SSLCommerz returned error:\n";
    echo "  Failed Reason: " . ($data['failedreason'] ?? 'Unknown') . "\n";
    print_r($data);
}
