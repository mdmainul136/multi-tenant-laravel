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
            CREATE TABLE IF NOT EXISTS ec_products (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE NOT NULL,
                sku VARCHAR(100) UNIQUE NOT NULL,
                description TEXT NULL,
                short_description VARCHAR(500) NULL,
                category VARCHAR(100) NULL,
                price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                sale_price DECIMAL(10, 2) NULL,
                cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                stock_quantity INT NOT NULL DEFAULT 0,
                weight DECIMAL(8, 2) NULL,
                dimensions VARCHAR(100) NULL,
                image_url VARCHAR(500) NULL,
                gallery JSON NULL,
                is_featured BOOLEAN DEFAULT FALSE,
                is_active BOOLEAN DEFAULT TRUE,
                meta_title VARCHAR(255) NULL,
                meta_description VARCHAR(500) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_slug (slug),
                INDEX idx_sku (sku),
                INDEX idx_category (category),
                INDEX idx_is_active (is_active),
                INDEX idx_is_featured (is_featured)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection('tenant_dynamic');
        $connection->statement("DROP TABLE IF EXISTS ec_products");
    }
};

