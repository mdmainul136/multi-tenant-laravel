<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

use App\Models\Tenant;
use App\Models\Module;
use App\Models\TenantModule;
use App\Services\ModuleService;
use App\Services\ModuleMigrationManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Create services
$migrationManager = new ModuleMigrationManager();
$moduleService = new ModuleService($migrationManager);

try {
    echo "🚀 Starting Subscription Flow Test...\n\n";

    // 1. Setup Test Tenant
    $tenantId = 'test-tenant-' . time();
    $dbName = 'tenant_' . str_replace('-', '_', $tenantId);
    
    echo "1️⃣ Creating test tenant: {$tenantId} (DB: {$dbName})\n";
    
    // Create tenant in master DB
    $tenant = Tenant::create([
        'tenant_id' => $tenantId,
        'tenant_name' => 'Test Subscription Tenant',
        'name' => 'Test User',
        'email' => "admin@{$tenantId}.com",
        'password' => bcrypt('password'),
        'database_name' => $dbName,
        'domain' => "{$tenantId}.localhost",
        'admin_email' => "admin@{$tenantId}.com", // Ensure this field exists now
    ]);
    
    // Create tenant DB
    echo "   Creating tenant database...\n";
    $dbManager = new \App\Services\DatabaseManager();
    $dbManager->createTenantDatabase($dbName);
    $dbManager->runTenantMigrations($dbName);
    
    // 2. Setup Test Module (POS)
    echo "2️⃣ Checking for POS module...\n";
    $module = Module::firstOrCreate(
        ['module_key' => 'pos'],
        [
            'module_name' => 'Point of Sale',
            'description' => 'Complete POS system',
            'price' => 49.99,
            'is_active' => true
        ]
    );
    echo "   Module ID: {$module->id}\n";

    // 3. Subscribe Tenant to Module
    echo "3️⃣ Subscribing tenant to POS module...\n";
    $result = $moduleService->subscribeModule($tenantId, 'pos', [
        'status' => 'active',
        'subscription_type' => 'monthly',
        'price_paid' => 49.99,
        'payment_id' => null // Simulating direct activation
    ]);

    if (!$result['success']) {
        throw new Exception("Subscription failed: " . $result['message']);
    }
    echo "   ✅ Subscription successful!\n";

    // 4. Verify tenant_modules table
    echo "4️⃣ Verifying master database records...\n";
    $subscription = TenantModule::where('tenant_id', $tenant->id)
        ->where('module_id', $module->id)
        ->first();
        
    if ($subscription && $subscription->status === 'active') {
        echo "   ✅ tenant_modules record exists and is active.\n";
    } else {
        throw new Exception("❌ tenant_modules record missing or inactive.");
    }

    // 5. Verify Tenant Database Migrations
    echo "5️⃣ Verifying tenant database tables...\n";
    
    // Switch connection to tenant DB manually for verification
    config(['database.connections.tenant_test' => array_merge(config('database.connections.mysql'), [
        'database' => $dbName
    ])]);
    
    $tenantHasTable = Schema::connection('tenant_test')->hasTable('pos_products');
    
    if ($tenantHasTable) {
        echo "   ✅ 'pos_products' table exists in {$dbName}!\n";
    } else {
        throw new Exception("❌ 'pos_products' table NOT found in {$dbName}. Migrations failed?");
    }
    
    // Clean up (optional)
    echo "\n🧹 Cleaning up test data...\n";
    // DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
    // $tenant->delete();
    // $subscription->delete();
    echo "   (Skipped cleanup for manual inspection)\n";

    echo "\n🎉 Test Passed Successfully!\n";

} catch (Exception $e) {
    $error = "\n❌ Test Failed: " . $e->getMessage() . "\n" . $e->getTraceAsString();
    echo $error;
    file_put_contents('test_result.log', $error, FILE_APPEND);
    exit(1);
}
