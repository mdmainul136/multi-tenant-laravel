<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    $columns = Illuminate\Support\Facades\DB::select('SHOW COLUMNS FROM tenants');
    foreach ($columns as $column) {
        echo $column->Field . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
