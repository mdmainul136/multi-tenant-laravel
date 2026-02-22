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
            CREATE TABLE IF NOT EXISTS pos_payments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sale_id BIGINT UNSIGNED NOT NULL,
                payment_method ENUM('cash', 'card', 'mobile', 'wallet', 'points') NOT NULL,
                amount DECIMAL(15, 2) NOT NULL,
                transaction_id VARCHAR(100) NULL,
                details JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (sale_id) REFERENCES pos_sales(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection('tenant_dynamic');
        $connection->statement("DROP TABLE IF EXISTS pos_payments");
    }
};

