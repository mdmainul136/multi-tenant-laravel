<?php

use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Models\TenantWallet;
use App\Modules\CrossBorderIOR\Controllers\HsCodeController;
use App\Services\DatabaseManager;
use App\Services\SaaSWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tenantId = 'acme22';
echo "--- HS Code Lookup Charging Verification (Tenant: {$tenantId}) ---\n";

// 1. Switch to Tenant Database
$dbManager = app(DatabaseManager::class);
$dbManager->switchToTenantDatabase($tenantId);

// 2. Ensure Logs table exists
if (!Schema::hasTable('ior_hs_lookup_logs')) {
    echo "Creating missing ior_hs_lookup_logs table...\n";
    Schema::create('ior_hs_lookup_logs', function ($table) {
        $table->id();
        $table->string('hs_code', 20)->index();
        $table->string('destination_country', 3)->index();
        $table->decimal('cost_usd', 10, 4)->default(0);
        $table->string('source')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
        $table->index(['hs_code', 'destination_country', 'created_at']);
    });
}

// 3. Check Starting Balance
$walletService = app(SaaSWalletService::class);
$initialBalance = $walletService->getBalance($tenantId);
echo "Initial Balance: \${$initialBalance}\n";

// Ensure enough balance for testing
if ($initialBalance < 1.0) {
    echo "Adding credit for testing...\n";
    $walletService->credit($tenantId, 10.0, 'test_topup', 'Adding test credit');
    $initialBalance = $walletService->getBalance($tenantId);
    echo "New Balance: \${$initialBalance}\n";
}

$controller = app(HsCodeController::class);

// 4. Test FREE Search
echo "\nTesting FREE Search...\n";
$requestSearch = Request::create('/api/ior/hs/search', 'GET', ['q' => 'laptop']);
$responseSearch = $controller->search($requestSearch);
$searchData = json_decode($responseSearch->getContent(), true);

if ($searchData['success'] && is_array($searchData['data'])) {
    echo "Search Success: Found " . count($searchData['data']) . " results.\n";
}
$balanceAfterSearch = $walletService->getBalance($tenantId);
if (abs($balanceAfterSearch - $initialBalance) < 0.001) {
    echo "Verification: Search is FREE (Balance unchanged).\n";
} else {
    echo "Verification: FAILED! Search charged balance.\n";
}

// 5. Test PAID Selection
$hsCode = '8471.30.00';
$country = 'BD';
$cost = config('ior_quotas.costs.hs_lookup', 0.18);

echo "\nTesting PAID Selection (HS: {$hsCode})...\n";
$requestSelect1 = Request::create('/api/ior/hs/select', 'POST', [
    'hs_code' => $hsCode,
    'destination_country' => $country
]);
$requestSelect1->attributes->set('tenant_id', $tenantId);

$responseSelect1 = $controller->select($requestSelect1);
$selectData1 = json_decode($responseSelect1->getContent(), true);

if ($selectData1['success'] && !($selectData1['cached'] ?? false)) {
    echo "Initial Selection: SUCCESS, Charged \${$cost}.\n";
} else {
    echo "Initial Selection: FAILED or unexpected status.\n";
    print_r($selectData1);
}

$balanceAfterSelect1 = $walletService->getBalance($tenantId);
if (abs($initialBalance - $balanceAfterSelect1 - $cost) < 0.001) {
    echo "Verification: Balance correctly debited by \${$cost}.\n";
} else {
    echo "Verification: FAILED! Balance debit incorrect. Expected \${$cost}, Actual Change: " . ($initialBalance - $balanceAfterSelect1) . "\n";
}

// 6. Test CACHED Selection (Free within 24h)
echo "\nTesting CACHED Selection (Same HS within 24h)...\n";
$responseSelect2 = $controller->select($requestSelect1);
$selectData2 = json_decode($responseSelect2->getContent(), true);

if ($selectData2['success'] && ($selectData2['cached'] ?? false)) {
    echo "Repeat Selection: SUCCESS, Fetched from cache.\n";
} else {
    echo "Repeat Selection: FAILED! Expected cached response.\n";
}

$balanceAfterSelect2 = $walletService->getBalance($tenantId);
if (abs($balanceAfterSelect1 - $balanceAfterSelect2) < 0.001) {
    echo "Verification: Repeat selection is FREE (Cache hit).\n";
} else {
    echo "Verification: FAILED! Repeat selection was charged.\n";
}

// 7. Cleanup
DB::table('ior_hs_lookup_logs')->where('hs_code', $hsCode)->delete();
echo "\n--- Verification Complete ---\n";
