<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = DB::connection('tenant_dynamic');
        
        $connection->statement("
            CREATE TABLE IF NOT EXISTS pos_products (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                sku VARCHAR(100) UNIQUE NOT NULL,
                barcode VARCHAR(100) UNIQUE NULL,
                description TEXT NULL,
                category VARCHAR(100) NULL,
                price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                stock_quantity INT NOT NULL DEFAULT 0,
                min_stock_level INT NOT NULL DEFAULT 0,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_sku (sku),
                INDEX idx_barcode (barcode),
                INDEX idx_category (category),
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection('tenant_dynamic');
        $connection->statement("DROP TABLE IF EXISTS pos_products");
    }
};

