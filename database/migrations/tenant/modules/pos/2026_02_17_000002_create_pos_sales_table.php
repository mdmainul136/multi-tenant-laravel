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
            CREATE TABLE IF NOT EXISTS pos_sales (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sale_number VARCHAR(50) UNIQUE NOT NULL,
                customer_name VARCHAR(255) NULL,
                customer_phone VARCHAR(20) NULL,
                subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                tax DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                discount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                payment_method ENUM('cash', 'card', 'mobile', 'other') DEFAULT 'cash',
                payment_status ENUM('paid', 'partial', 'pending') DEFAULT 'paid',
                notes TEXT NULL,
                sold_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_sale_number (sale_number),
                INDEX idx_payment_status (payment_status),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (sold_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection('tenant_dynamic');
        $connection->statement("DROP TABLE IF EXISTS pos_sales");
    }
};

