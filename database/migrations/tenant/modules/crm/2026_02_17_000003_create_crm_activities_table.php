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
            CREATE TABLE IF NOT EXISTS crm_activities (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                type ENUM('call', 'email', 'meeting', 'task', 'note', 'other') NOT NULL,
                subject VARCHAR(255) NOT NULL,
                description TEXT NULL,
                contact_id BIGINT UNSIGNED NULL,
                deal_id BIGINT UNSIGNED NULL,
                assigned_to BIGINT UNSIGNED NULL,
                status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
                priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
                due_date DATETIME NULL,
                completed_at DATETIME NULL,
                duration INT NULL COMMENT 'Duration in minutes',
                outcome TEXT NULL,
                created_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_type (type),
                INDEX idx_contact_id (contact_id),
                INDEX idx_deal_id (deal_id),
                INDEX idx_assigned_to (assigned_to),
                INDEX idx_status (status),
                INDEX idx_due_date (due_date),
                FOREIGN KEY (contact_id) REFERENCES crm_contacts(id) ON DELETE CASCADE,
                FOREIGN KEY (deal_id) REFERENCES crm_deals(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection('tenant_dynamic');
        $connection->statement("DROP TABLE IF EXISTS crm_activities");
    }
};

