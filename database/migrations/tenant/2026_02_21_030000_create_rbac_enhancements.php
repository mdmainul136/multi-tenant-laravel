<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('tenant_dynamic');

        // Staff Activity Logs
        $connection->statement("
            CREATE TABLE IF NOT EXISTS staff_activity_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NULL,
                user_name VARCHAR(255) NOT NULL DEFAULT 'System',
                action VARCHAR(100) NOT NULL,
                module VARCHAR(100) NOT NULL,
                resource VARCHAR(100) NULL,
                resource_id BIGINT UNSIGNED NULL,
                details JSON NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_action (action),
                INDEX idx_module (module),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 2FA columns on users table
        $connection->statement("
            ALTER TABLE users
            ADD COLUMN two_factor_secret TEXT NULL,
            ADD COLUMN two_factor_enabled BOOLEAN DEFAULT FALSE,
            ADD COLUMN two_factor_confirmed_at TIMESTAMP NULL
        ");

        // Enhance roles table
        $connection->statement("
            ALTER TABLE roles
            ADD COLUMN description VARCHAR(500) NULL,
            ADD COLUMN is_system BOOLEAN DEFAULT FALSE
        ");

        // Enhance permissions table
        $connection->statement("
            ALTER TABLE permissions
            ADD COLUMN description VARCHAR(500) NULL
        ");
    }

    public function down(): void
    {
        $connection = DB::connection('tenant_dynamic');
        $connection->statement("DROP TABLE IF EXISTS staff_activity_logs");
        $connection->statement("ALTER TABLE users DROP COLUMN two_factor_secret, DROP COLUMN two_factor_enabled, DROP COLUMN two_factor_confirmed_at");
        $connection->statement("ALTER TABLE roles DROP COLUMN description, DROP COLUMN is_system");
        $connection->statement("ALTER TABLE permissions DROP COLUMN description");
    }
};

