<?php

use App\Models\LandlordIorHsCode;
use Illuminate\Http\Request;
use App\Modules\CrossBorderIOR\Controllers\LandlordIorAdminController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "--- Landlord Global HS Code Management Verification ---\n";

// 1. Setup Controller
$controller = app(LandlordIorAdminController::class);

// 2. Test CREATE (BD)
echo "\nTesting Store HS Code (BD)...\n";
$hsCode = '9999.00.00';
$requestStore = Request::create('/api/ior/landlord/hs-codes', 'POST', [
    'hs_code'      => $hsCode,
    'country_code' => 'BGD',
    'category_en'  => 'Test Gadget',
    'cd'           => 10.0,
    'vat'          => 15.0,
]);
$responseStore = $controller->storeHsCode($requestStore);
$resultStore = json_decode($responseStore->getContent(), true);

if ($resultStore['success']) {
    echo "SUCCESS: HS Code created for BGD (ID: " . $resultStore['data']['id'] . ")\n";
    $idBd = $resultStore['data']['id'];
} else {
    echo "FAILED: " . ($resultStore['message'] ?? 'Unknown error') . "\n";
    exit(1);
}

// 3. Test CREATE (USA - Same HS Code)
echo "\nTesting Store HS Code (USA - Same code)...\n";
$requestStoreUsa = Request::create('/api/ior/landlord/hs-codes', 'POST', [
    'hs_code'      => $hsCode,
    'country_code' => 'USA',
    'category_en'  => 'Test Gadget (USA Variant)',
    'cd'           => 0.0,
    'vat'          => 0.0,
]);
$responseStoreUsa = $controller->storeHsCode($requestStoreUsa);
$resultStoreUsa = json_decode($responseStoreUsa->getContent(), true);

if ($resultStoreUsa['success']) {
    echo "SUCCESS: HS Code created for USA (ID: " . $resultStoreUsa['data']['id'] . ")\n";
    $idUsa = $resultStoreUsa['data']['id'];
} else {
    echo "FAILED: " . ($resultStoreUsa['message'] ?? 'Unknown error') . "\n";
}

// 4. Test LIST with country filter
echo "\nTesting List with Filter (USA)...\n";
$requestList = Request::create('/api/ior/landlord/hs-codes', 'GET', ['country' => 'USA']);
$responseList = $controller->hsCodes($requestList);
$resultList = json_decode($responseList->getContent(), true);

echo "Found " . count($resultList['data']) . " codes for USA.\n";
if (count($resultList['data']) === 1 && $resultList['data'][0]['hs_code'] === $hsCode) {
    echo "Verification: Correct filtering.\n";
} else {
    echo "Verification: FAILED filtering.\n";
}

// 5. Test UPDATE
echo "\nTesting Update HS Code...\n";
$requestUpdate = Request::create('/api/ior/landlord/hs-codes/' . $idBd, 'PUT', [
    'category_en' => 'Updated Test Gadget',
    'rd'          => 5.0
]);
$responseUpdate = $controller->updateHsCode($requestUpdate, $idBd);
$resultUpdate = json_decode($responseUpdate->getContent(), true);

if ($resultUpdate['success'] && $resultUpdate['data']['rd'] == 5.0) {
    echo "SUCCESS: HS Code updated.\n";
} else {
    echo "FAILED update.\n";
}

// 6. Cleanup & Test DELETE
echo "\nTesting Delete HS Code...\n";
$controller->destroyHsCode($idBd);
$controller->destroyHsCode($idUsa);

$check = LandlordIorHsCode::find($idBd);
if (!$check) {
    echo "SUCCESS: HS Code deleted.\n";
} else {
    echo "FAILED delete.\n";
}

echo "\n--- Verification Complete ---\n";
