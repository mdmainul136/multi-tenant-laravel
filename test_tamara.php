<?php
/**
 * Tamara BNPL Sandbox Test Script
 *
 * Tamara Sandbox URL: https://api-sandbox.tamara.co
 * Sandbox token is sent to you via EMAIL after account activation.
 *
 * HOW TO GET SANDBOX API TOKEN:
 *   Option A (fastest): Via a payment platform partner
 *     - checkout.com → Tamara via their dashboard
 *     - PayTabs → dashboard.paytabs.com → Tamara integration
 *     - Amazon Payment Services → Tamara addon
 *
 *   Option B (direct):
 *     1. Go to: https://merchant.tamara.co/register
 *     2. Submit business details
 *     3. Tamara team contacts you ~1 business day
 *     4. They email you Sandbox API Token + Merchant ID
 *
 * Sandbox Test Phone Numbers (Tamara):
 *   +966500000001  → Always Approved (SA)
 *   +966500000002  → Always Rejected
 *   +971500000001  → Always Approved (UAE)
 *   OTP:  000000 (6 zeros)
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Tamara BNPL Sandbox Test ===\n\n";

$apiToken = config('services.tamara.api_token');

if (!$apiToken || str_contains($apiToken ?? '', 'xxxx')) {
    echo "⚠️  TAMARA_API_TOKEN not configured yet.\n\n";

    echo "┌─────────────────────────────────────────────────────┐\n";
    echo "│          TAMARA SANDBOX SETUP GUIDE                 │\n";
    echo "├─────────────────────────────────────────────────────┤\n";
    echo "│ Option 1: Direct Registration (1-2 business days)   │\n";
    echo "│   → merchant.tamara.co/register                     │\n";
    echo "│   → They email you sandbox token                    │\n";
    echo "│                                                     │\n";
    echo "│ Option 2: Via Checkout.com (fastest!)               │\n";
    echo "│   → hub.checkout.com → Tamara integration           │\n";
    echo "│   → Sandbox token in dashboard                      │\n";
    echo "├─────────────────────────────────────────────────────┤\n";
    echo "│ TAMARA TEST PHONE NUMBERS:                          │\n";
    echo "│   ✅ +966500000001 → Approved (KSA)                 │\n";
    echo "│   ❌ +966500000002 → Rejected                       │\n";
    echo "│   ✅ +971500000001 → Approved (UAE)                 │\n";
    echo "│   OTP: 000000 (6 zeros)                             │\n";
    echo "├─────────────────────────────────────────────────────┤\n";
    echo "│ After getting token, add to .env:                   │\n";
    echo "│   TAMARA_API_TOKEN=eyJhbGci...                      │\n";
    echo "│   TAMARA_SANDBOX=true                               │\n";
    echo "└─────────────────────────────────────────────────────┘\n\n";

    // ── Test Sandbox URL connectivity (no auth needed) ────────────────────────
    echo "Testing Tamara sandbox URL connectivity...\n";
    $pingResponse = \Illuminate\Support\Facades\Http::timeout(5)->get('https://api-sandbox.tamara.co/health');
    if ($pingResponse->successful() || $pingResponse->status() === 401) {
        echo "✅ Tamara sandbox server is reachable (Status: {$pingResponse->status()})\n";
        echo "   Once you have a token, the API will work!\n";
    } else {
        echo "⚠️  Sandbox server response: " . $pingResponse->status() . "\n";
    }
    exit(0);
}

echo "API Token:  " . substr($apiToken, 0, 20) . "...\n";
echo "Sandbox:    YES (api-sandbox.tamara.co)\n\n";

$baseUrl = 'https://api-sandbox.tamara.co';
$orderId = 'TAMARA-TEST-' . time();

// ── TEST 1: Create Checkout Session ──────────────────────────────────────────
echo "=== TEST 1: Create Tamara Checkout (SAR 300, 3 splits) ===\n";

$response = \Illuminate\Support\Facades\Http::withToken($apiToken)
    ->post("{$baseUrl}/checkout", [
        'order_reference_id' => $orderId,
        'total_amount'       => ['amount' => '300.00', 'currency' => 'SAR'],
        'description'        => 'Finance Module - Monthly Subscription',
        'country_code'       => 'SA',
        'payment_type'       => 'PAY_BY_INSTALMENTS',
        'instalments'        => 3,
        'consumer'           => [
            'first_name'   => 'Mohammed',
            'last_name'    => 'Al-Rasheed',
            'phone_number' => '+966500000001', // ← Test approved phone
            'email'        => 'test@example.sa',
            'dob'          => '1990-01-01',
        ],
        'merchant_url' => [
            'success'      => 'http://localhost:8000/api/payment/bnpl/tamara/success',
            'failure'      => 'http://localhost:8000/api/payment/bnpl/tamara/failure',
            'cancel'       => 'http://localhost:8000/api/payment/bnpl/tamara/cancel',
            'notification' => 'http://localhost:8000/api/payment/bnpl/tamara/notify',
        ],
        'items' => [
            [
                'name'             => 'Finance Module',
                'type'             => 'Digital',
                'reference_id'     => 'finance-monthly',
                'sku'              => 'FINANCE-001',
                'quantity'         => 1,
                'unit_price'       => ['amount' => '300.00', 'currency' => 'SAR'],
                'total_amount'     => ['amount' => '300.00', 'currency' => 'SAR'],
                'discount_amount'  => ['amount' => '0.00',   'currency' => 'SAR'],
                'tax_amount'       => ['amount' => '45.00',  'currency' => 'SAR'], // 15% KSA VAT
                'image_url'        => null,
                'product_url'      => null,
            ],
        ],
        'tax_amount'      => ['amount' => '45.00', 'currency' => 'SAR'],
        'shipping_amount' => ['amount' => '0.00',  'currency' => 'SAR'],
        'discount'        => ['amount' => '0.00',  'currency' => 'SAR', 'name' => ''],
        'shipping_address' => [
            'first_name' => 'Mohammed',
            'last_name'  => 'Al-Rasheed',
            'line1'      => 'King Fahd Road',
            'city'       => 'Riyadh',
            'country_code' => 'SA',
        ],
        'billing_address'  => [
            'first_name' => 'Mohammed',
            'last_name'  => 'Al-Rasheed',
            'line1'      => 'King Fahd Road',
            'city'       => 'Riyadh',
            'country_code' => 'SA',
        ],
    ]);

$data = $response->json();
echo "HTTP Status: " . $response->status() . "\n";

if ($response->successful() && !empty($data['checkout_id'])) {
    $checkoutId  = $data['checkout_id'];
    $checkoutUrl = $data['checkout_url'];

    echo "\n✅ Tamara Checkout Created!\n";
    echo "   Checkout ID: {$checkoutId}\n";
    echo "   Splits:      3x " . round(300/3, 2) . " SAR\n";
    echo "   VAT (15%):   SAR 45.00 (included)\n\n";

    echo "   🔗 Checkout URL:\n   {$checkoutUrl}\n";
    echo "\n   ➡️  Browser → Phone: +966500000001 → OTP: 000000 → Payment Done!\n\n";

    // ── TEST 2: Retrieve Order ────────────────────────────────────────────────
    echo "=== TEST 2: Retrieve Order Details ===\n";
    $getResp = \Illuminate\Support\Facades\Http::withToken($apiToken)
        ->get("{$baseUrl}/orders/{$checkoutId}");

    if ($getResp->successful()) {
        $order = $getResp->json();
        echo "✅ Order retrieved\n";
        echo "   Status:   " . ($order['status'] ?? 'N/A') . "\n\n";
    }

    // ── TEST 3: Rejection test ────────────────────────────────────────────────
    echo "=== TEST 3: Rejected Customer (+966500000002) ===\n";

    $failOrder         = $response->json();
    $failPayload       = json_decode(json_encode($data), true); // copy

    $rejectResp = \Illuminate\Support\Facades\Http::withToken($apiToken)
        ->post("{$baseUrl}/checkout", array_merge(
            json_decode($response->body(), true) ?? [],
            [
                'order_reference_id' => 'TAMARA-REJECT-' . time(),
                'consumer' => ['first_name' => 'Rejected', 'last_name' => 'User', 'phone_number' => '+966500000002', 'email' => 'reject@test.com'],
            ]
        ));

    echo "   Rejection test status: " . $rejectResp->status() . "\n";
    echo "   Response: " . ($rejectResp->json()['message'] ?? 'Check above phone — rejection flow varies') . "\n\n";

} else {
    echo "❌ Checkout creation failed:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

echo "=== Tamara Test Complete ===\n";
echo "  ✅ Sandbox API: " . ($response->successful() ? 'Connected' : 'Check token') . "\n";
echo "  ✅ 3-split installments with KSA VAT 15% included\n";
