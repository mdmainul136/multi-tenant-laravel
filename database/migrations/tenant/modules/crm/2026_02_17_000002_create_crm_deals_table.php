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
            CREATE TABLE IF NOT EXISTS crm_deals (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                contact_id BIGINT UNSIGNED NULL,
                value DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                currency VARCHAR(3) DEFAULT 'USD',
                stage ENUM('lead', 'qualified', 'proposal', 'negotiation', 'closed_won', 'closed_lost') DEFAULT 'lead',
                probability INT DEFAULT 0,
                expected_close_date DATE NULL,
                actual_close_date DATE NULL,
                assigned_to BIGINT UNSIGNED NULL,
                source VARCHAR(100) NULL,
                description TEXT NULL,
                notes TEXT NULL,
                tags JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_contact_id (contact_id),
                INDEX idx_stage (stage),
                INDEX idx_assigned_to (assigned_to),
                INDEX idx_expected_close_date (expected_close_date),
                FOREIGN KEY (contact_id) REFERENCES crm_contacts(id) ON DELETE SET NULL,
                FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection('tenant_dynamic');
        $connection->statement("DROP TABLE IF EXISTS crm_deals");
    }
};

