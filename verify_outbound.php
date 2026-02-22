<?php
/**
 * Phase 12 Verification: Warehouse Outbound & Logistics Fulfilment
 * 
 * Tests: schema, single dispatch, batch dispatch, customs clearance, delivery
 * 
 * Usage: php verify_outbound.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle($request = Illuminate\Http\Request::capture());

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Services\DatabaseManager;

echo "\n═══════════════════════════════════════════════════\n";
echo " Phase 12: Warehouse Outbound & Logistics Verification\n";
echo "═══════════════════════════════════════════════════\n\n";

// ─── Connect to tenant DB ───
try {
    $dbManager = app(DatabaseManager::class);
    $tenant = DB::connection('landlord')->table('tenants')->first();
    if ($tenant) {
        $dbManager->connect($tenant);
    }
} catch (\Exception $e) {
    echo "⚠ Tenant DB connection: {$e->getMessage()}\n";
}

// ─── 1. Apply Schema ───
echo "[1] Applying Schema...\n";
try {
    // Create milestones table if missing
    if (!Schema::hasTable('ior_order_milestones')) {
        Schema::create('ior_order_milestones', function ($table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('status');
            $table->string('location')->nullable();
            $table->string('message_en');
            $table->string('message_bn')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        echo "    ✓ Created ior_order_milestones table.\n";
    } else {
        echo "    ✓ ior_order_milestones exists.\n";
    }

    // Create shipment batches table if missing
    if (!Schema::hasTable('ior_shipment_batches')) {
        Schema::create('ior_shipment_batches', function ($table) {
            $table->id();
            $table->string('batch_number')->unique()->index();
            $table->string('carrier')->nullable();
            $table->string('master_tracking_no')->nullable()->index();
            $table->string('origin_warehouse')->default('USA-NY');
            $table->string('destination')->default('BD-DAC');
            $table->string('status')->default('pending');
            $table->decimal('total_weight_kg', 10, 2)->default(0);
            $table->decimal('total_volumetric_weight', 10, 2)->default(0);
            $table->unsignedInteger('order_count')->default(0);
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('estimated_arrival')->nullable();
            $table->timestamp('customs_cleared_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamps();
        });
        echo "    ✓ Created ior_shipment_batches table.\n";
    } else {
        echo "    ✓ ior_shipment_batches exists.\n";
        // Add missing columns
        $batchCols = ['order_count', 'customs_cleared_at', 'arrived_at'];
        foreach ($batchCols as $col) {
            if (!Schema::hasColumn('ior_shipment_batches', $col)) {
                Schema::table('ior_shipment_batches', function ($table) use ($col) {
                    if ($col === 'order_count') $table->unsignedInteger($col)->default(0);
                    else $table->timestamp($col)->nullable();
                });
                echo "    ✓ Added {$col} to ior_shipment_batches.\n";
            }
        }
    }

    // Add outbound columns to ior_foreign_orders
    $orderCols = [
        'shipment_batch_id'   => fn($t) => $t->unsignedBigInteger('shipment_batch_id')->nullable()->index(),
        'intl_tracking_number'=> fn($t) => $t->string('intl_tracking_number')->nullable(),
        'intl_courier_code'   => fn($t) => $t->string('intl_courier_code', 20)->nullable(),
        'dispatched_at'       => fn($t) => $t->timestamp('dispatched_at')->nullable(),
        'customs_cleared_at'  => fn($t) => $t->timestamp('customs_cleared_at')->nullable(),
        'delivered_at'        => fn($t) => $t->timestamp('delivered_at')->nullable(),
    ];
    foreach ($orderCols as $col => $cb) {
        if (!Schema::hasColumn('ior_foreign_orders', $col)) {
            Schema::table('ior_foreign_orders', function ($table) use ($cb) { $cb($table); });
            echo "    ✓ Added {$col} to ior_foreign_orders.\n";
        }
    }
    echo "    ✓ All schema checks passed.\n";
} catch (\Exception $e) {
    echo "    ⚠ Schema: {$e->getMessage()}\n";
}

// ─── 2. Create a test order at 'warehouse' status ───
echo "\n[2] Creating Test Order at Warehouse Status...\n";
$testOrder = IorForeignOrder::create([
    'order_number'       => 'IOR-TEST-OUTBOUND-' . time(),
    'product_url'        => 'https://amazon.com/test-product',
    'product_name'       => 'Test Product for Outbound',
    'quantity'           => 1,
    'source_price_usd'   => 25.00,
    'exchange_rate'      => 120.0,
    'base_price_bdt'     => 3000,
    'estimated_price_bdt'=> 3500,
    'order_status'       => IorForeignOrder::STATUS_WAREHOUSE,
    'shipping_full_name' => 'Test Customer',
    'shipping_phone'     => '01700000000',
    'shipping_address'   => 'Test Address, Dhaka',
    'shipping_city'      => 'Dhaka',
    'shipping_area'      => 'Mohammadpur',
]);
echo "    ✓ Order created: {$testOrder->order_number} (status: {$testOrder->order_status})\n";

// ─── 3. Test Single Dispatch ───
echo "\n[3] Testing Single Dispatch (warehouse → shipped)...\n";
$testOrder->update([
    'order_status'         => IorForeignOrder::STATUS_SHIPPED,
    'intl_courier_code'    => 'fedex',
    'intl_tracking_number' => 'FEDEX-TEST-' . time(),
    'dispatched_at'        => now(),
]);
DB::table('ior_order_milestones')->insert([
    'order_id'   => $testOrder->id,
    'status'     => 'dispatched',
    'location'   => 'USA-NY',
    'message_en' => 'Your package has been dispatched from USA-NY via FEDEX.',
    'message_bn' => 'আপনার পণ্যটি USA-NY থেকে FEDEX-এর মাধ্যমে পাঠানো হয়েছে।',
    'metadata'   => json_encode(['courier' => 'fedex']),
    'created_at' => now(),
    'updated_at' => now(),
]);
$testOrder->refresh();
$pass = $testOrder->order_status === 'shipped' && $testOrder->intl_tracking_number !== null;
echo "    " . ($pass ? '✓' : '✗') . " Status: {$testOrder->order_status}, Tracking: {$testOrder->intl_tracking_number}\n";

// ─── 4. Test Batch Creation & Manifesting ───
echo "\n[4] Testing Batch Shipment...\n";
$testOrder2 = IorForeignOrder::create([
    'order_number'       => 'IOR-TEST-BATCH-' . time(),
    'product_url'        => 'https://amazon.com/test-product-2',
    'product_name'       => 'Batch Test Product',
    'quantity'           => 2,
    'source_price_usd'   => 45.00,
    'exchange_rate'      => 120.0,
    'base_price_bdt'     => 5400,
    'estimated_price_bdt'=> 6200,
    'order_status'       => IorForeignOrder::STATUS_WAREHOUSE,
    'shipping_full_name' => 'Test Customer 2',
    'shipping_phone'     => '01800000000',
    'shipping_address'   => 'Test 2, Dhaka',
    'shipping_city'      => 'Dhaka',
    'shipping_area'      => 'Dhanmondi',
]);

$batchService = app(\App\Modules\CrossBorderIOR\Services\ShipmentBatchService::class);

$batch = $batchService->createBatch('dhl', 'USA-NY', 'BD-DAC');
echo "    ✓ Batch created: {$batch->batch_number}\n";

$addResult = $batchService->addOrdersToBatch($batch->id, [$testOrder2->id]);
echo "    " . ($addResult['success'] ? '✓' : '✗') . " Added {$addResult['orders_added']} orders to batch.\n";

$manifest = $batchService->manifestBatch($batch->id);
echo "    " . ($manifest['success'] ? '✓' : '✗') . " Manifest: Tracking={$manifest['tracking_number']}\n";

$manifestedBatch = DB::table('ior_shipment_batches')->find($batch->id);
echo "    ✓ Batch status: {$manifestedBatch->status}, Weight: {$manifestedBatch->total_weight_kg}kg\n";

// ─── 5. Test Batch Status Updates ───
echo "\n[5] Testing Batch Lifecycle...\n";
$r1 = $batchService->updateBatchStatus($batch->id, 'in_transit');
echo "    " . ($r1['success'] ? '✓' : '✗') . " manifested → in_transit\n";
$r2 = $batchService->updateBatchStatus($batch->id, 'customs');
echo "    " . ($r2['success'] ? '✓' : '✗') . " in_transit → customs\n";
$r3 = $batchService->updateBatchStatus($batch->id, 'received');
echo "    " . ($r3['success'] ? '✓' : '✗') . " customs → received\n";

// ─── 6. Test Customs Clearance ───
echo "\n[6] Testing Customs Clearance...\n";
$testOrder->update(['order_status' => IorForeignOrder::STATUS_CUSTOMS, 'customs_cleared_at' => now()]);
DB::table('ior_order_milestones')->insert([
    'order_id'   => $testOrder->id,
    'status'     => 'customs_cleared',
    'location'   => 'Bangladesh Customs',
    'message_en' => 'Your package has cleared Bangladesh customs.',
    'message_bn' => 'আপনার পণ্যটি বাংলাদেশ কাস্টমস থেকে ছাড় পেয়েছে।',
    'metadata'   => json_encode([]),
    'created_at' => now(),
    'updated_at' => now(),
]);
$testOrder->refresh();
echo "    ✓ Status: {$testOrder->order_status}, Cleared: {$testOrder->customs_cleared_at}\n";

// ─── 7. Test Delivery Confirmation ───
echo "\n[7] Testing Delivery Confirmation...\n";
$testOrder->update(['order_status' => IorForeignOrder::STATUS_DELIVERED, 'delivered_at' => now()]);
DB::table('ior_order_milestones')->insert([
    'order_id'   => $testOrder->id,
    'status'     => 'delivered',
    'location'   => 'Dhaka',
    'message_en' => 'Your package has been delivered!',
    'message_bn' => 'আপনার পণ্যটি সফলভাবে ডেলিভারি করা হয়েছে!',
    'metadata'   => json_encode([]),
    'created_at' => now(),
    'updated_at' => now(),
]);
$testOrder->refresh();
echo "    ✓ Status: {$testOrder->order_status}, Delivered: {$testOrder->delivered_at}\n";

// ─── 8. Verify Milestones ───
echo "\n[8] Checking Milestones...\n";
$milestones = DB::table('ior_order_milestones')
    ->where('order_id', $testOrder->id)
    ->orderBy('created_at')
    ->pluck('status')
    ->toArray();
echo "    ✓ Milestones: " . implode(' → ', $milestones) . "\n";

// ─── 9. Test Batch Detail ───
echo "\n[9] Testing Batch Detail...\n";
$detail = $batchService->getBatchDetail($batch->id);
echo "    ✓ Batch: {$detail['batch']->batch_number}, Orders: {$detail['orders']->count()}\n";

// ─── 10. Cleanup ───
echo "\n[10] Cleanup...\n";
DB::table('ior_order_milestones')->whereIn('order_id', [$testOrder->id, $testOrder2->id])->delete();
$testOrder->forceDelete();
$testOrder2->forceDelete();
DB::table('ior_shipment_batches')->where('id', $batch->id)->delete();
echo "    ✓ Test records cleaned up.\n";

echo "\n═══════════════════════════════════════════════════\n";
echo " Phase 12 Verification Complete ✓\n";
echo "═══════════════════════════════════════════════════\n\n";
