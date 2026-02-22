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
            ALTER TABLE pos_sales 
            ADD COLUMN session_id BIGINT UNSIGNED NULL AFTER sale_number,
            ADD COLUMN customer_id BIGINT UNSIGNED NULL AFTER session_id,
            ADD COLUMN cash_received DECIMAL(15, 2) NOT NULL DEFAULT 0.00 AFTER total,
            ADD COLUMN change_amount DECIMAL(15, 2) NOT NULL DEFAULT 0.00 AFTER cash_received,
            ADD COLUMN points_earned INT NOT NULL DEFAULT 0 AFTER change_amount,
            ADD COLUMN points_redeemed INT NOT NULL DEFAULT 0 AFTER points_earned,
            ADD CONSTRAINT fk_pos_sales_session FOREIGN KEY (session_id) REFERENCES pos_sessions(id) ON DELETE SET NULL,
            ADD CONSTRAINT fk_pos_sales_customer FOREIGN KEY (customer_id) REFERENCES ec_customers(id) ON DELETE SET NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection('tenant_dynamic');
        $connection->statement("
            ALTER TABLE pos_sales 
            DROP FOREIGN KEY fk_pos_sales_session,
            DROP FOREIGN KEY fk_pos_sales_customer,
            DROP COLUMN session_id,
            DROP COLUMN customer_id,
            DROP COLUMN cash_received,
            DROP COLUMN change_amount,
            DROP COLUMN points_earned,
            DROP COLUMN points_redeemed
        ");
    }
};

