<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetupMasterDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup master database for multi-tenant application';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Setting up master database for multi-tenant application...');

        try {
            $databaseName = config('database.connections.mysql.database');
            
            // Create connection to MySQL without database selection
            $this->info("Creating database: {$databaseName}");
            
            $host = config('database.connections.mysql.host');
            $port = config('database.connections.mysql.port');
            $username = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');
            
            // Use PDO to create database without selecting one first
            $pdo = new \PDO("mysql:host={$host};port={$port}", $username, $password);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = null; // Close connection
            
            $this->info("✅ Database '{$databaseName}' created successfully");

            // Run migrations
            $this->info('Running migrations...');
            $this->call('migrate', ['--force' => true]);

            $this->newLine();
            $this->info('✅ Master database setup completed successfully!');
            $this->info('');
            $this->info('Next steps:');
            $this->info('1. Start the Laravel development server: php artisan serve');
            $this->info('2. Create your first tenant via API or run: php artisan tenant:create');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error setting up master database: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
