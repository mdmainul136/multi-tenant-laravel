#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Adding missing column to tenants table...\n\n";

try {
    // Check if column exists
    $columns = DB::select("SHOW COLUMNS FROM tenants LIKE 'admin_email'");
    
    if (empty($columns)) {
        echo "Adding admin_email column...\n";
        DB::statement("ALTER TABLE tenants ADD COLUMN admin_email VARCHAR(255) AFTER email");
        echo "✅ admin_email column added successfully!\n\n";
    } else {
        echo "✅ admin_email column already exists!\n\n";
    }
    
    // Show all columns
    $output = "Current tenants table columns:\n";
    $allColumns = DB::select("SHOW COLUMNS FROM tenants");
    foreach ($allColumns as $col) {
        $output .= "  - {$col->Field} ({$col->Type})\n";
    }
    echo $output;
    file_put_contents('patch_log.txt', $output, FILE_APPEND);
    
} catch (\Exception $e) {
    $error = "❌ Error: " . $e->getMessage() . "\n";
    echo $error;
    file_put_contents('patch_error.txt', $error);
    exit(1);
}
