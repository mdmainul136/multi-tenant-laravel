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
            CREATE TABLE IF NOT EXISTS ec_orders (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_number VARCHAR(50) UNIQUE NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                order_type ENUM('local', 'cross_border') DEFAULT 'local',
                status ENUM('pending', 'processing', 'completed', 'cancelled', 'refunded') DEFAULT 'pending',
                payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
                payment_method VARCHAR(50) NULL,
                subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                tax DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                shipping DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                discount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                currency VARCHAR(3) DEFAULT 'USD',
                billing_address TEXT NULL,
                shipping_address TEXT NULL,
                customer_note TEXT NULL,
                admin_note TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_order_number (order_number),
                INDEX idx_customer_id (customer_id),
                INDEX idx_status (status),
                INDEX idx_payment_status (payment_status),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (customer_id) REFERENCES ec_customers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection('tenant_dynamic');
        $connection->statement("DROP TABLE IF EXISTS ec_orders");
    }
};

