<?php

use App\Modules\CrossBorderIOR\Services\LandedCostCalculatorService;
use App\Models\LandlordIorCountry;
use App\Models\LandlordIorRestrictedItem;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// 1. Setup Global Governance Data
LandlordIorCountry::updateOrCreate(
    ['code' => 'USA'],
    ['name' => 'USA', 'default_shipping_rate_per_kg' => 12.00, 'is_active' => true]
);

LandlordIorRestrictedItem::updateOrCreate(
    ['keyword' => 'laser'],
    ['reason' => 'High-power lasers are restricted.', 'severity' => 'blocking', 'is_active' => true]
);

\App\Models\LandlordIorHsCode::updateOrCreate(
    ['hs_code' => '8471.30.00'],
    ['category_en' => 'Laptops (Global)', 'cd' => 0, 'rd' => 0, 'sd' => 0, 'vat' => 15, 'ait' => 5, 'at' => 5]
);

$simulator = app(LandedCostCalculatorService::class);

// 2. Test Simulation for USA product (should use $12/kg from Landlord and Global HS code)
$params = [
    'price_usd' => 100,
    'weight_kg' => 2,
    'origin_country' => 'USA',
    'hs_code' => '8471.30.00',
    'title' => 'Industrial Laser Pointer'
];

$result = $simulator->simulate($params);

echo "--- IOR Global Governance Verification ---\n";
echo "Origin Country: " . $result['input']['origin_country'] . "\n";
echo "Applied Shipping Rate: $" . $result['shipping']['rate_per_kg'] . "/kg (Expect 12.00)\n";
echo "HS Code Dictionary Source: " . ($result['customs']['source'] ?? $result['customs']['breakdown']['source'] ?? 'unknown') . "\n";
echo "Compliance Flagged: " . ($result['compliance']['is_restricted'] ? "YES" : "NO") . "\n";
echo "Governance Applied: " . ($result['governance_applied'] ? "YES" : "NO") . "\n";

if ($result['shipping']['rate_per_kg'] == 12.00 && $result['compliance']['is_restricted'] && $result['governance_applied']) {
    echo "VERIFICATION STATUS: SUCCESS\n";
} else {
    echo "VERIFICATION STATUS: FAILED\n";
    print_r($result);
}
