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
        
        // 1. Marketing Audiences (Segments)
        $connection->statement("
            CREATE TABLE IF NOT EXISTS marketing_audiences (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT NULL,
                type ENUM('dynamic', 'static') DEFAULT 'dynamic',
                rules JSON NULL, -- Logic for dynamic filtering (e.g., total_spent > 1000)
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 2. Marketing Templates
        $connection->statement("
            CREATE TABLE IF NOT EXISTS marketing_templates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                channel ENUM('email', 'sms', 'whatsapp') NOT NULL,
                subject VARCHAR(255) NULL, -- For Email
                content TEXT NOT NULL,
                placeholders JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 3. Marketing Campaigns
        $connection->statement("
            CREATE TABLE IF NOT EXISTS marketing_campaigns (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                audience_id BIGINT UNSIGNED NULL,
                channel ENUM('email', 'sms', 'whatsapp') NOT NULL,
                status ENUM('draft', 'scheduled', 'sending', 'completed', 'failed', 'paused') DEFAULT 'draft',
                scheduled_at TIMESTAMP NULL,
                started_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                settings JSON NULL, -- Channel specific settings (sender id, provider, etc.)
                is_ab_test BOOLEAN DEFAULT FALSE,
                ab_test_config JSON NULL, -- Ratio, winning criteria
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (audience_id) REFERENCES marketing_audiences(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 4. Campaign Variants (for A/B Testing)
        $connection->statement("
            CREATE TABLE IF NOT EXISTS marketing_campaign_variants (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                campaign_id BIGINT UNSIGNED NOT NULL,
                template_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(100) NOT NULL, -- e.g., 'Variant A', 'Variant B'
                ratio DECIMAL(5, 2) DEFAULT 0.00, -- Percentage of audience
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns(id) ON DELETE CASCADE,
                FOREIGN KEY (template_id) REFERENCES marketing_templates(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 5. Campaign Logs (Individual deliveries)
        $connection->statement("
            CREATE TABLE IF NOT EXISTS marketing_campaign_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                campaign_id BIGINT UNSIGNED NOT NULL,
                variant_id BIGINT UNSIGNED NULL,
                customer_id BIGINT UNSIGNED NULL,
                recipient VARCHAR(255) NOT NULL, -- Email or Phone
                status ENUM('pending', 'sent', 'delivered', 'opened', 'clicked', 'failed', 'bounced', 'unsubscribed') DEFAULT 'pending',
                external_id VARCHAR(255) NULL, -- Provider's message ID
                error_message TEXT NULL,
                metadata JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_campaign_status (campaign_id, status),
                INDEX idx_recipient (recipient),
                FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns(id) ON DELETE CASCADE,
                FOREIGN KEY (variant_id) REFERENCES marketing_campaign_variants(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection('tenant_dynamic');
        $connection->statement("DROP TABLE IF EXISTS marketing_campaign_logs");
        $connection->statement("DROP TABLE IF EXISTS marketing_campaign_variants");
        $connection->statement("DROP TABLE IF EXISTS marketing_campaigns");
        $connection->statement("DROP TABLE IF EXISTS marketing_templates");
        $connection->statement("DROP TABLE IF EXISTS marketing_audiences");
    }
};

