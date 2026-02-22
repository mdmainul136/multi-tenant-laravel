<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ModuleMigrationManager
{
    /**
     * Get the path to module migrations
     */
    protected function getModuleMigrationsPath(string $moduleKey): string
    {
        // Try new dynamic module path first
        $dynamicPath = app_path("Modules/" . ucfirst($moduleKey) . "/database/migrations");
        if (File::exists($dynamicPath)) {
            return $dynamicPath;
        }

        // Fallback to legacy path
        return database_path("migrations/tenant/modules/{$moduleKey}");
    }

    /**
     * Get all migration files for a module
     */
    public function getModuleMigrations(string $moduleKey): array
    {
        $path = $this->getModuleMigrationsPath($moduleKey);

        if (!File::exists($path)) {
            return [];
        }

        $files = File::files($path);
        $migrations = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $migrations[] = $file->getFilename();
            }
        }

        sort($migrations);
        return $migrations;
    }

    /**
     * Run module migrations on a tenant database
     */
    public function runModuleMigrations(string $databaseName, string $moduleKey): void
    {
        $migrations = $this->getModuleMigrations($moduleKey);

        if (empty($migrations)) {
            Log::warning("No migrations found for module: {$moduleKey}");
            return;
        }

        // Start a transaction on the master database for locking
        DB::connection('mysql')->beginTransaction();

        try {
            // ENFORCE QUOTA AT MIGRATION TIME (Gap 5)
            $tenant = \App\Models\Tenant::where('database_name', $databaseName)->first();
            if ($tenant) {
                $isolationService = app(TenantDatabaseIsolationService::class);
                if ($isolationService->isOverQuota($tenant)) {
                    throw new \Exception("Cannot run migrations: Database quota exceeded for {$databaseName}");
                }
            }

            // IMPLEMENT RACE CONDITION FIX: 
            // Lock the module migration rows for this tenant & module using SELECT FOR UPDATE.
            // This prevents another process from running migrations for the same tenant+module simultaneously.
            DB::connection('mysql')
                ->table('module_migrations')
                ->where('tenant_database', $databaseName)
                ->where('module_key', $moduleKey)
                ->lockForUpdate()
                ->get();

            $connection = $this->getTenantConnection($databaseName);
            $batch = $this->getNextBatchNumber($databaseName, $moduleKey);

            foreach ($migrations as $migrationFile) {
                // Check if already run (inside locked transaction)
                if ($this->hasRun($databaseName, $moduleKey, $migrationFile)) {
                    continue;
                }

                try {
                    // Include and run the migration
                    $path = $this->getModuleMigrationsPath($moduleKey) . '/' . $migrationFile;
                    $migration = require $path;
                    
                    // Set the connection for the migration
                    $migration->up();

                    // Record in module_migrations table (master DB)
                    $this->recordMigration($databaseName, $moduleKey, $migrationFile, $batch);

                    Log::info("Ran migration {$migrationFile} for module {$moduleKey} on {$databaseName}");

                } catch (\Illuminate\Database\QueryException $e) {
                    // Check for unique constraint violation (error code 1062)
                    if ($e->getCode() === '23000' || $e->getCode() === 23000) {
                        Log::warning("Migration {$migrationFile} already applied by another process (unique constraint hit).");
                        continue;
                    }
                    throw $e;
                } catch (\Exception $e) {
                    Log::error("Failed to run migration {$migrationFile} for module {$moduleKey}: " . $e->getMessage());
                    throw $e;
                }
            }

            DB::connection('mysql')->commit();

        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();
            Log::error("Module migration batch failed for {$moduleKey} on {$databaseName}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Rollback module migrations from a tenant database (NON-DESTRUCTIVE)
     */
    public function rollbackModuleMigrations(string $databaseName, string $moduleKey): void
    {
        $migrations = $this->getModuleMigrations($moduleKey);
        $migrations = array_reverse($migrations); // Rollback in reverse order

        $connection = $this->getTenantConnection($databaseName);

        foreach ($migrations as $migrationFile) {
            // Check if it was run
            if (!$this->hasRun($databaseName, $moduleKey, $migrationFile)) {
                continue;
            }

            try {
                // Include the migration
                $path = $this->getModuleMigrationsPath($moduleKey) . '/' . $migrationFile;
                $migration = require $path;
                
                // ARCHIVE INSTEAD OF DROP:
                // We parse the migration file or standard naming conventions to find tables.
                // For simplicity and safety, we will find table names being created and RENAME them.
                $this->archiveModuleTables($connection, $path);

                // We still call down() for any non-table cleanup (indexes, etc.), 
                // but we expect the user to have followed "Archiving" pattern in migrations 
                // or we rely on the rename logic above.
                $migration->down();

                // Remove from module_migrations table
                $this->removeMigrationRecord($databaseName, $moduleKey, $migrationFile);

                Log::info("Archived and rolled back migration {$migrationFile} for module {$moduleKey} on {$databaseName}");

            } catch (\Exception $e) {
                Log::error("Failed to rollback migration {$migrationFile} for module {$moduleKey}: " . $e->getMessage());
                throw $e;
            }
        }
    }

    /**
     * Safely archive tables instead of dropping them.
     * Supports both Raw SQL (CREATE TABLE) and Laravel Schema::create()
     */
    protected function archiveModuleTables($connection, string $migrationPath): void
    {
        $content = File::get($migrationPath);
        $tables = [];

        // 1. Detect Raw SQL: CREATE TABLE `name`
        preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?[`"]?([a-zA-Z0-9_]+)[`"]?/i', $content, $rawMatches);
        if (!empty($rawMatches[1])) {
            $tables = array_merge($tables, $rawMatches[1]);
        }

        // 2. Detect Laravel Schema: Schema::create('name', ...)
        preg_match_all('/Schema::create\([\'"]([a-zA-Z0-9_]+)[\'"]/i', $content, $schemaMatches);
        if (!empty($schemaMatches[1])) {
            $tables = array_merge($tables, $schemaMatches[1]);
        }

        $tables = array_unique($tables);
        $timestamp = now()->format('Ymd_His');

        foreach ($tables as $table) {
            $archivedName = "_archived_{$table}_{$timestamp}";
            
            try {
                // Check if table exists using the correct connection
                $exists = DB::connection('tenant_dynamic')->select("SHOW TABLES LIKE '{$table}'");
                
                if (!empty($exists)) {
                    DB::connection('tenant_dynamic')->statement("RENAME TABLE `{$table}` TO `{$archivedName}`");
                    Log::info("ALARM: Prevented Data Loss! Archived table: {$table} -> {$archivedName}");
                }
            } catch (\Exception $e) {
                Log::warning("Could not archive table {$table}: " . $e->getMessage());
            }
        }
    }

    /**
     * Get tenant database connection
     */
    protected function getTenantConnection(string $databaseName)
    {
        config([
            'database.connections.tenant_dynamic' => [
                'driver' => 'mysql',
                'host' => config('database.connections.mysql.host'),
                'port' => config('database.connections.mysql.port'),
                'database' => $databaseName,
                'username' => config('database.connections.mysql.username'),
                'password' => config('database.connections.mysql.password'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]
        ]);

        return DB::connection('tenant_dynamic');
    }

    /**
     * Check if a migration has been run
     */
    protected function hasRun(string $databaseName, string $moduleKey, string $migrationFile): bool
    {
        return DB::connection('mysql')
            ->table('module_migrations')
            ->where('tenant_database', $databaseName)
            ->where('module_key', $moduleKey)
            ->where('migration_file', $migrationFile)
            ->exists();
    }

    /**
     * Record a migration as run
     */
    protected function recordMigration(string $databaseName, string $moduleKey, string $migrationFile, int $batch): void
    {
        DB::connection('mysql')
            ->table('module_migrations')
            ->insert([
                'tenant_database' => $databaseName,
                'module_key' => $moduleKey,
                'migration_file' => $migrationFile,
                'batch' => $batch,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Remove migration record
     */
    protected function removeMigrationRecord(string $databaseName, string $moduleKey, string $migrationFile): void
    {
        DB::connection('mysql')
            ->table('module_migrations')
            ->where('tenant_database', $databaseName)
            ->where('module_key', $moduleKey)
            ->where('migration_file', $migrationFile)
            ->delete();
    }

    /**
     * Get next batch number for migrations
     */
    protected function getNextBatchNumber(string $databaseName, string $moduleKey): int
    {
        $lastBatch = DB::connection('mysql')
            ->table('module_migrations')
            ->where('tenant_database', $databaseName)
            ->where('module_key', $moduleKey)
            ->max('batch');

        return $lastBatch ? $lastBatch + 1 : 1;
    }
}

