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
            CREATE TABLE IF NOT EXISTS ec_order_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                product_name VARCHAR(255) NOT NULL,
                sku VARCHAR(100) NOT NULL,
                quantity INT NOT NULL DEFAULT 1,
                unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_order_id (order_id),
                INDEX idx_product_id (product_id),
                FOREIGN KEY (order_id) REFERENCES ec_orders(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES ec_products(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection('tenant_dynamic');
        $connection->statement("DROP TABLE IF EXISTS ec_order_items");
    }
};

