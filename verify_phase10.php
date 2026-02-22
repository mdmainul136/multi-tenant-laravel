<?php

use App\Models\CrossBorderIOR\IorForeignOrder;
use Illuminate\Http\Request;
use App\Modules\CrossBorderIOR\Controllers\IorWarehouseController;
use App\Services\DatabaseManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tenantId = 'acme22';
echo "--- Phase 10: Warehouse Inbound Management Verification (Tenant: {$tenantId}) ---\n";

// 1. Switch to Tenant Database
echo "Switching to tenant database...\n";
$dbManager = app(DatabaseManager::class);
$dbManager->switchToTenantDatabase($tenantId);

// 2. Recreate Milestone table with correct foreign key
echo "Recreating ior_order_milestones table with correct foreign key...\n";
Schema::dropIfExists('ior_order_milestones');
Schema::create('ior_order_milestones', function ($table) {
    $table->id();
    $table->unsignedBigInteger('order_id')->index();
    $table->string('status');
    $table->string('location')->nullable();
    $table->string('message_en');
    $table->string('message_bn')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->foreign('order_id')->references('id')->on('ior_foreign_orders')->onDelete('cascade');
});

// 3. Setup Dummy Order
$trackingNo = 'TEST-TN-' . time();
$order = IorForeignOrder::create([
    'order_number'      => 'IOR-TEST-' . time(),
    'product_name'      => 'Test Widget',
    'product_url'       => 'https://example.com',
    'quantity'          => 1,
    'tracking_number'   => $trackingNo,
    'order_status'      => 'ordered',
    'scraped_data'      => ['warehouse_location' => 'USA-NY'],
]);

echo "Created Dummy Order: {$order->order_number} (ID: {$order->id}) with Tracking: {$trackingNo}\n";

// 4. Simulate Warehouse Scan
echo "\nSimulating Warehouse Scan for Identifier: {$trackingNo}...\n";

$request = Request::create('/api/ior/admin/warehouse/receive', 'POST', [
    'identifier' => $trackingNo,
    'location'   => 'USA-NY',
    'note'       => 'Arrived in good condition.'
]);

$controller = app(IorWarehouseController::class);
$response = $controller->receive($request);

$result = json_decode($response->getContent(), true);

if ($result['success'] ?? false) {
    echo "Result: SUCCESS (" . $result['message'] . ")\n";
    
    // Verify DB update
    $updatedOrder = IorForeignOrder::find($order->id);
    if ($updatedOrder->order_status === 'warehouse') {
        echo "Verification: Status correctly updated to 'warehouse'.\n";
    } else {
        echo "Verification: FAILED! Status is " . $updatedOrder->order_status . "\n";
    }

    // Verify Milestone
    $milestone = DB::table('ior_order_milestones')->where('order_id', $order->id)->first();
    if ($milestone && $milestone->status === 'warehouse_received') {
        echo "Verification: Milestone 'warehouse_received' recorded.\n";
    } else {
        echo "Verification: FAILED! Milestone not found.\n";
    }
} else {
    echo "Result: FAILED (" . ($result['message'] ?? 'Unknown error') . ")\n";
    if (isset($result['error'])) echo "Error Detail: " . $result['error'] . "\n";
}

// Clean up (Keep milestones for manual inspection if needed, or delete)
$order->delete();
// DB::table('ior_order_milestones')->where('order_id', $order->id)->delete(); // Deleted by cascade

echo "\n--- Verification Complete ---\n";
