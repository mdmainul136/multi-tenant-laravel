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
            CREATE TABLE IF NOT EXISTS crm_contacts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(255) UNIQUE NULL,
                phone VARCHAR(20) NULL,
                mobile VARCHAR(20) NULL,
                company VARCHAR(255) NULL,
                job_title VARCHAR(100) NULL,
                address TEXT NULL,
                city VARCHAR(100) NULL,
                state VARCHAR(100) NULL,
                country VARCHAR(100) NULL,
                postal_code VARCHAR(20) NULL,
                website VARCHAR(255) NULL,
                linkedin VARCHAR(255) NULL,
                twitter VARCHAR(255) NULL,
                source VARCHAR(100) NULL,
                status ENUM('lead', 'prospect', 'customer', 'inactive') DEFAULT 'lead',
                assigned_to BIGINT UNSIGNED NULL,
                notes TEXT NULL,
                tags JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_email (email),
                INDEX idx_company (company),
                INDEX idx_status (status),
                INDEX idx_assigned_to (assigned_to),
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
        $connection->statement("DROP TABLE IF EXISTS crm_contacts");
    }
};

