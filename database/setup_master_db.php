#!/usr/bin/env php
<?php

/**
 * Master Database Setup Script
 * 
 * This script will completely rebuild the master database
 * Run: php database/setup_master_db.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "🔧 Master Database Setup\n";
echo "========================\n\n";

try {
    // Drop all tables in correct order (reverse of creation)
    echo "1️⃣ Dropping existing tables...\n";
    
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    
    $tables = [
        'invoices',
        'payment_methods',
        'payments',
        'tenant_modules',
        'modules',
        'tenants',
        'users',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'migrations'
    ];
    
    foreach ($tables as $table) {
        DB::statement("DROP TABLE IF EXISTS `{$table}`");
        echo "   ✓ Dropped {$table}\n";
    }
    
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    echo "\n";

    // Create migrations table
    echo "2️⃣ Creating migrations table...\n";
    DB::statement("
        CREATE TABLE `migrations` (
          `id` int unsigned NOT NULL AUTO_INCREMENT,
          `migration` varchar(255) NOT NULL,
          `batch` int NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Created migrations table\n\n";

    // Create tenants table
    echo "3️⃣ Creating tenants table...\n";
    DB::statement("
        CREATE TABLE `tenants` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `tenant_id` varchar(255) NOT NULL,
          `tenant_name` varchar(255) NOT NULL,
          `company_name` varchar(255) DEFAULT NULL,
          `business_type` varchar(100) DEFAULT NULL,
          `admin_name` varchar(255) DEFAULT NULL,
          `name` varchar(255) NOT NULL,
          `domain` varchar(255) DEFAULT NULL,
          `database_name` varchar(255) NOT NULL,
          `email` varchar(255) DEFAULT NULL,
          `admin_email` varchar(255) DEFAULT NULL,
          `phone` varchar(20) DEFAULT NULL,
          `address` text,
          `city` varchar(100) DEFAULT NULL,
          `country` varchar(100) DEFAULT NULL,
          `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
          `created_at` timestamp NULL DEFAULT NULL,
          `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `tenants_tenant_id_unique` (`tenant_id`),
          UNIQUE KEY `tenants_database_name_unique` (`database_name`),
          KEY `tenants_status_index` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Created tenants table\n\n";

    // Create users table
    echo "4️⃣ Creating users table...\n";
    DB::statement("
        CREATE TABLE `users` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `email` varchar(255) NOT NULL,
          `email_verified_at` timestamp NULL DEFAULT NULL,
          `password` varchar(255) NOT NULL,
          `role` enum('super_admin','tenant_admin','user') NOT NULL DEFAULT 'user',
          `status` enum('active','inactive') NOT NULL DEFAULT 'active',
          `remember_token` varchar(100) DEFAULT NULL,
          `created_at` timestamp NULL DEFAULT NULL,
          `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `users_email_unique` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Created users table\n\n";

    // Create password_reset_tokens table
    echo "5️⃣ Creating password_reset_tokens table...\n";
    DB::statement("
        CREATE TABLE `password_reset_tokens` (
          `email` varchar(255) NOT NULL,
          `token` varchar(255) NOT NULL,
          `created_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Created password_reset_tokens table\n\n";

    // Create sessions table
    echo "6️⃣ Creating sessions table...\n";
    DB::statement("
        CREATE TABLE `sessions` (
          `id` varchar(255) NOT NULL,
          `user_id` bigint unsigned DEFAULT NULL,
          `ip_address` varchar(45) DEFAULT NULL,
          `user_agent` text,
          `payload` longtext NOT NULL,
          `last_activity` int NOT NULL,
          PRIMARY KEY (`id`),
          KEY `sessions_user_id_index` (`user_id`),
          KEY `sessions_last_activity_index` (`last_activity`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Created sessions table\n\n";

    // Create cache table
    echo "7️⃣ Creating cache table...\n";
    DB::statement("
        CREATE TABLE `cache` (
          `key` varchar(255) NOT NULL,
          `value` mediumtext NOT NULL,
          `expiration` int NOT NULL,
          PRIMARY KEY (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Created cache table\n\n";

    // Create cache_locks table
    echo "8️⃣ Creating cache_locks table...\n";
    DB::statement("
        CREATE TABLE `cache_locks` (
          `key` varchar(255) NOT NULL,
          `owner` varchar(255) NOT NULL,
          `expiration` int NOT NULL,
          PRIMARY KEY (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Created cache_locks table\n\n";

    // Create jobs table
    echo "9️⃣ Creating jobs table...\n";
    DB::statement("
        CREATE TABLE `jobs` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `queue` varchar(255) NOT NULL,
          `payload` longtext NOT NULL,
          `attempts` tinyint unsigned NOT NULL,
          `reserved_at` int unsigned DEFAULT NULL,
          `available_at` int unsigned NOT NULL,
          `created_at` int unsigned NOT NULL,
          PRIMARY KEY (`id`),
          KEY `jobs_queue_index` (`queue`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Created jobs table\n\n";

    // Create job_batches table
    echo "🔟 Creating job_batches table...\n";
    DB::statement("
        CREATE TABLE `job_batches` (
          `id` varchar(255) NOT NULL,
          `name` varchar(255) NOT NULL,
          `total_jobs` int NOT NULL,
          `pending_jobs` int NOT NULL,
          `failed_jobs` int NOT NULL,
          `failed_job_ids` longtext NOT NULL,
          `options` mediumtext,
          `cancelled_at` int DEFAULT NULL,
          `created_at` int NOT NULL,
          `finished_at` int DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Created job_batches table\n\n";

    // Create failed_jobs table
    echo "1️⃣1️⃣ Creating failed_jobs table...\n";
    DB::statement("
        CREATE TABLE `failed_jobs` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `uuid` varchar(255) NOT NULL,
          `connection` text NOT NULL,
          `queue` text NOT NULL,
          `payload` longtext NOT NULL,
          `exception` longtext NOT NULL,
          `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Created failed_jobs table\n\n";

    // Create modules table
    echo "4️⃣ Creating modules table...\n";
    DB::statement("
        CREATE TABLE `modules` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `module_key` varchar(255) NOT NULL,
          `module_name` varchar(255) NOT NULL,
          `description` text,
          `version` varchar(255) DEFAULT '1.0.0',
          `is_active` tinyint(1) NOT NULL DEFAULT '1',
          `price` decimal(10,2) NOT NULL DEFAULT '0.00',
          `created_at` timestamp NULL DEFAULT NULL,
          `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `modules_module_key_unique` (`module_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Created modules table\n\n";

    // Create module_migrations table
    echo "4️⃣.5️⃣ Creating module_migrations table...\n";
    DB::statement("
        CREATE TABLE `module_migrations` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `tenant_database` varchar(255) NOT NULL,
          `module_key` varchar(255) NOT NULL,
          `migration_file` varchar(255) NOT NULL,
          `batch` int NOT NULL,
          `created_at` timestamp NULL DEFAULT NULL,
          `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `module_migrations_tenant_database_index` (`tenant_database`),
          KEY `module_migrations_module_key_index` (`module_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Created module_migrations table\n\n";

    // Create tenant_modules table
    echo "5️⃣ Creating tenant_modules table...\n";
    DB::statement("
        CREATE TABLE `tenant_modules` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `tenant_id` bigint unsigned NOT NULL,
          `module_id` bigint unsigned NOT NULL,
          `subscription_type` enum('monthly','annual','lifetime') NOT NULL DEFAULT 'monthly',
          `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
          `subscribed_at` timestamp NULL DEFAULT NULL,
          `expires_at` timestamp NULL DEFAULT NULL,
          `created_at` timestamp NULL DEFAULT NULL,
          `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `tenant_modules_tenant_id_foreign` (`tenant_id`),
          KEY `tenant_modules_module_id_foreign` (`module_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Created tenant_modules table\n\n";

    // Create payments table
    echo "6️⃣ Creating payments table...\n";
    DB::statement("
        CREATE TABLE `payments` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `tenant_id` bigint unsigned NOT NULL,
          `module_id` bigint unsigned NOT NULL,
          `amount` decimal(10,2) NOT NULL,
          `currency` varchar(3) NOT NULL DEFAULT 'USD',
          `payment_method` varchar(255) NOT NULL,
          `payment_status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
          `transaction_id` varchar(255) DEFAULT NULL,
          `stripe_session_id` varchar(255) DEFAULT NULL,
          `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
          `metadata` json DEFAULT NULL,
          `created_at` timestamp NULL DEFAULT NULL,
          `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `payments_tenant_id_index` (`tenant_id`),
          KEY `payments_payment_status_index` (`payment_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Created payments table\n\n";

    // Create payment_methods table
    echo "7️⃣ Creating payment_methods table...\n";
    DB::statement("
        CREATE TABLE `payment_methods` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `tenant_id` bigint unsigned NOT NULL,
          `stripe_payment_method_id` varchar(255) NOT NULL,
          `type` varchar(255) NOT NULL DEFAULT 'card',
          `brand` varchar(255) DEFAULT NULL,
          `last4` varchar(4) DEFAULT NULL,
          `exp_month` int DEFAULT NULL,
          `exp_year` int DEFAULT NULL,
          `is_default` tinyint(1) NOT NULL DEFAULT '0',
          `is_active` tinyint(1) NOT NULL DEFAULT '1',
          `created_at` timestamp NULL DEFAULT NULL,
          `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `payment_methods_stripe_payment_method_id_unique` (`stripe_payment_method_id`),
          KEY `payment_methods_tenant_id_index` (`tenant_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Created payment_methods table\n\n";

    // Create invoices table
    echo "8️⃣ Creating invoices table...\n";
    DB::statement("
        CREATE TABLE `invoices` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `tenant_id` bigint unsigned NOT NULL,
          `payment_id` bigint unsigned DEFAULT NULL,
          `module_id` bigint unsigned NOT NULL,
          `invoice_number` varchar(255) NOT NULL,
          `invoice_date` date NOT NULL,
          `due_date` date DEFAULT NULL,
          `subscription_type` varchar(255) NOT NULL,
          `subtotal` decimal(10,2) NOT NULL,
          `tax` decimal(10,2) NOT NULL DEFAULT '0.00',
          `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
          `total` decimal(10,2) NOT NULL,
          `status` enum('draft','paid','pending','cancelled','refunded') NOT NULL DEFAULT 'pending',
          `notes` text,
          `metadata` json DEFAULT NULL,
          `created_at` timestamp NULL DEFAULT NULL,
          `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
          KEY `invoices_invoice_number_index` (`invoice_number`),
          KEY `invoices_tenant_id_index` (`tenant_id`),
          KEY `invoices_status_index` (`status`),
          KEY `invoices_invoice_date_index` (`invoice_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Created invoices table\n\n";

    // Insert migration records
    echo "9️⃣ Recording migrations...\n";
    $migrations = [
        '2026_02_17_120000_create_modules_table',
        '2026_02_17_140000_create_invoices_table',
        '2026_02_17_141000_create_payment_methods_table',
    ];
    
    foreach ($migrations as $migration) {
        DB::table('migrations')->insert([
            'migration' => $migration,
            'batch' => 1
        ]);
    }
    echo "   ✓ Recorded migrations\n\n";

    echo "✅ Master database setup complete!\n\n";
    echo "📋 Next Steps:\n";
    echo "   1. Run: php artisan db:seed --class=ModuleSeeder\n";
    echo "   2. Create a tenant if needed\n";
    echo "   3. Test the application\n\n";

} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
