<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Services\DatabaseManager;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tenantId = 'acme22';
echo "Enhancing ior_hs_lookup_logs table for tenant: {$tenantId}...\n";

try {
    $dbManager = app(DatabaseManager::class);
    $dbManager->switchToTenantDatabase($tenantId);
    
    //switchToTenantDatabase sets 'tenant_dynamic' as default, but let's be explicit
    $connection = 'tenant_dynamic';

    Schema::connection($connection)->table('ior_hs_lookup_logs', function (Blueprint $table) use ($connection) {
        if (!Schema::connection($connection)->hasColumn('ior_hs_lookup_logs', 'type')) {
            $table->string('type')->default('selection')->after('id')->index();
            echo "Added column: type\n";
        }
        if (!Schema::connection($connection)->hasColumn('ior_hs_lookup_logs', 'product_name')) {
            $table->string('product_name')->nullable()->after('hs_code');
            echo "Added column: product_name\n";
        }
        if (!Schema::connection($connection)->hasColumn('ior_hs_lookup_logs', 'input_hash')) {
            $table->string('input_hash', 64)->nullable()->index()->after('product_name');
            echo "Added column: input_hash\n";
        }
        if (!Schema::connection($connection)->hasColumn('ior_hs_lookup_logs', 'provider')) {
            $table->string('provider')->nullable()->after('source');
            echo "Added column: provider\n";
        }
        if (!Schema::connection($connection)->hasColumn('ior_hs_lookup_logs', 'raw_response')) {
            $table->json('raw_response')->nullable()->after('metadata');
            echo "Added column: raw_response\n";
        }
    });
    echo "Schema enhancement SUCCESS for tenant: {$tenantId}.\n";
} catch (\Exception $e) {
    echo "Schema enhancement FAILED: " . $e->getMessage() . "\n";
}
