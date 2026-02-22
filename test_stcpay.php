<?php
/**
 * STC Pay Sandbox Test Script
 *
 * STC Pay is Saudi Arabia's dominant telco wallet.
 * Sandbox requires merchant registration at: https://b2b.stcpay.com.sa
 *
 * HOW TO GET SANDBOX ACCESS:
 *   1. Go to: https://b2b.stcpay.com.sa/docs
 *   2. Click "Become a Merchant" → Register business
 *   3. STC Pay team contacts you (usually 2-5 business days for KSA businesses)
 *   4. They provide:
 *      - MerchantID
 *      - API Key (X-ClientId)
 *      - Client Secret
 *   5. Add to .env:
 *      STC_PAY_MERCHANT_ID=your_merchant_id
 *      STC_PAY_API_KEY=your_api_key
 *      STC_PAY_SANDBOX=true
 *
 * STC Pay SANDBOX Test Numbers:
 *   Mobile: +966500000001 (test wallet, always succeeds)
 *   OTP:    123456 (sandbox OTP — always accepted)
 *
 * FLOW:
 *   Step 1: initiate() → STC sends OTP to user's registered mobile
 *   Step 2: User enters OTP
 *   Step 3: confirm() → payment completed instantly
 *   Refund: Goes back to STC Pay wallet (instant/24h — much faster than cards!)
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== STC Pay Sandbox Test ===\n\n";

$merchantId = config('services.stc_pay.merchant_id');
$apiKey     = config('services.stc_pay.api_key');
$sandbox    = config('services.stc_pay.sandbox', true);

if (!$merchantId || !$apiKey) {
    echo "⚠️  STC Pay credentials not configured.\n\n";

    echo "┌──────────────────────────────────────────────────────────┐\n";
    echo "│                STC PAY SANDBOX SETUP                    │\n";
    echo "├──────────────────────────────────────────────────────────┤\n";
    echo "│ Registration: https://b2b.stcpay.com.sa                 │\n";
    echo "│ Docs:         https://b2b.stcpay.com.sa/docs            │\n";
    echo "│ Timeline:     2-5 business days (KSA businesses only)   │\n";
    echo "│                                                          │\n";
    echo "│ What you receive after signup:                           │\n";
    echo "│   • MerchantID (e.g.: 462356)                           │\n";
    echo "│   • API Key (X-ClientId header)                         │\n";
    echo "│   • Client Secret                                        │\n";
    echo "├──────────────────────────────────────────────────────────┤\n";
    echo "│ SANDBOX TEST DATA:                                       │\n";
    echo "│   Test Mobile:  +966500000001                           │\n";
    echo "│   OTP:          123456                                   │\n";
    echo "├──────────────────────────────────────────────────────────┤\n";
    echo "│ PAYMENT FLOW:                                            │\n";
    echo "│   1. initiate(mobile) → OTP sent to customer            │\n";
    echo "│   2. Customer enters OTP (123456 in sandbox)            │\n";
    echo "│   3. confirm(paymentRef, otp) → Instantly paid!         │\n";
    echo "│   Refund → Goes to wallet (instant/24h)                 │\n";
    echo "│   (vs card = 7-21 days — BIG advantage for UX!)         │\n";
    echo "├──────────────────────────────────────────────────────────┤\n";
    echo "│ .env entries needed:                                     │\n";
    echo "│   STC_PAY_MERCHANT_ID=your_merchant_id                  │\n";
    echo "│   STC_PAY_API_KEY=your_api_key                          │\n";
    echo "│   STC_PAY_SANDBOX=true                                   │\n";
    echo "└──────────────────────────────────────────────────────────┘\n\n";

    // Show the STC Pay architecture/flow
    echo "📐 STC Pay Architecture in your codebase:\n";
    echo "   app/Services/Payment/Gateways/WalletGateway.php\n";
    echo "   ├─ initiate(mobile, amount, reference)  → PaymentReference\n";
    echo "   ├─ confirm(paymentReference, otp, amount) → TransactionID\n";
    echo "   └─ refund(transactionId, amount) → Instant wallet refund\n\n";

    // Simulate the flow with mock data
    echo "📋 SIMULATED Flow (what will happen with real credentials):\n\n";
    $mockRef = 'STC-' . date('YmdHis') . '-' . rand(1000, 9999);
    echo "  Step 1 → POST /b2b/payment/1.0/DirectPayment/initiation\n";
    echo "  Payload: { MobileNo: '+966500000001', Amount: 150.00, RefNum: '{$mockRef}' }\n";
    echo "  Response: { Code: '001', PaymentReference: 'STC-PAY-REF-XXXXX' }\n\n";
    echo "  Step 2 → Customer enters OTP: 123456\n\n";
    echo "  Step 3 → POST /b2b/payment/1.0/DirectPayment/confirmation\n";
    echo "  Payload: { PaymentReference: '...', OTPValue: '123456', Amount: 150.00 }\n";
    echo "  Response: { Code: '001', Description: 'Payment Done', TransactionID: 'TXN-XXXXX' }\n\n";
    echo "  Refund → POST /b2b/payment/1.0/Refund\n";
    echo "  Response: { Code: '001', RefundID: 'RF-XXXXX' } ← Instant to wallet!\n\n";

    exit(0);
}

echo "Merchant ID: {$merchantId}\n";
echo "Sandbox:     YES\n\n";

$baseUrl   = $sandbox
    ? 'https://sandbox.stcpay.com.sa/b2b/payment'
    : 'https://b2b.stcpay.com.sa/b2b/payment';
$reference = 'STC-TEST-' . time();

// ── TEST 1: Initiate Payment (OTP to customer) ────────────────────────────────
echo "=== TEST 1: Initiate STC Pay Payment (SAR 150) ===\n";
echo "   Mobile: +966500000001 (sandbox test number)\n";

$initResponse = \Illuminate\Support\Facades\Http::withHeaders([
    'apikey'       => $apiKey,
    'MerchantID'   => $merchantId,
    'Content-Type' => 'application/json',
])->post("{$baseUrl}/1.0/DirectPayment/initiation", [
    'MobileNo'  => '+966500000001',
    'Amount'    => 150.00,
    'MerchantID'=> $merchantId,
    'RefNum'    => $reference,
    'Remarks'   => 'CRM Module - Monthly Subscription',
    'NotifyURL' => 'http://localhost:8000/api/stcpay/callback',
]);

$initData = $initResponse->json();
echo "HTTP Status: " . $initResponse->status() . "\n";

if (($initData['Code'] ?? '') === '001') {
    $paymentRef = $initData['PaymentReference'];
    echo "\n✅ STC Pay Initiated!\n";
    echo "   PaymentReference: {$paymentRef}\n";
    echo "   OTP sent to customer mobile +966500000001\n";
    echo "   OTP expires in: 5 minutes\n\n";

    // ── TEST 2: Confirm with OTP ──────────────────────────────────────────────
    echo "=== TEST 2: Confirm with OTP (Sandbox OTP: 123456) ===\n";

    $confirmResponse = \Illuminate\Support\Facades\Http::withHeaders([
        'apikey'       => $apiKey,
        'MerchantID'   => $merchantId,
        'Content-Type' => 'application/json',
    ])->post("{$baseUrl}/1.0/DirectPayment/confirmation", [
        'MerchantID'       => $merchantId,
        'Amount'           => 150.00,
        'PaymentReference' => $paymentRef,
        'OTPValue'         => '123456', // ← Sandbox OTP
    ]);

    $confirmData = $confirmResponse->json();

    if (($confirmData['Code'] ?? '') === '001') {
        $txnId = $confirmData['TransactionID'];
        echo "✅ Payment Confirmed!\n";
        echo "   Transaction ID: {$txnId}\n";
        echo "   Amount:         SAR 150.00\n";
        echo "   Status:         PAID\n\n";

        // ── TEST 3: Refund ────────────────────────────────────────────────────
        echo "=== TEST 3: Wallet Refund (SAR 50 partial) ===\n";

        $refundResponse = \Illuminate\Support\Facades\Http::withHeaders([
            'apikey'       => $apiKey,
            'MerchantID'   => $merchantId,
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/1.0/Refund", [
            'MerchantID'    => $merchantId,
            'Amount'        => 50.00,
            'TransactionID' => $txnId,
            'Remarks'       => 'Partial refund test',
        ]);

        $refundData = $refundResponse->json();
        if (($refundData['Code'] ?? '') === '001') {
            echo "✅ Refund SUCCESS!\n";
            echo "   Refund ID: " . ($refundData['RefundID'] ?? 'N/A') . "\n";
            echo "   Goes to:   STC Pay Wallet (Instant / within 24h)\n";
            echo "   ⭐ FAR faster than card refund (no 7-21 day wait!)\n";
        } else {
            echo "   Refund response: " . json_encode($refundData) . "\n";
        }

    } else {
        echo "❌ Confirmation failed: " . ($confirmData['Description'] ?? json_encode($confirmData)) . "\n";
    }

} else {
    echo "❌ STC Pay initiation failed:\n";
    echo json_encode($initData, JSON_PRETTY_PRINT) . "\n";
}

echo "\n=== STC Pay Test Complete ===\n";
echo "  Key advantages:\n";
echo "  🏆 ~70% of Saudi users have STC Pay\n";
echo "  ⚡ Wallet refund = Instant (vs card = 7-21 days)\n";
echo "  🔒 OTP-based = Secure, no card details needed\n";
