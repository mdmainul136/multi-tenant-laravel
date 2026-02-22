<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TenantBackupService
{
    /**
     * Trigger a database backup for a specific tenant.
     * 
     * @param Tenant $tenant
     * @param string $context (e.g., 'unsubscribe', 'delete', 'manual')
     * @return string|null Path to the backup file
     */
    public function backup(Tenant $tenant, string $context = 'manual'): ?string
    {
        $dbName = $tenant->database_name;
        $timestamp = now()->format('Ymd_His');
        $filename = "backups/{$tenant->tenant_id}/{$dbName}_{$context}_{$timestamp}.sql";
        
        Log::info("Starting backup for tenant: {$tenant->tenant_id} (Context: {$context})");

        try {
            // Ensure directory exists
            Storage::makeDirectory("backups/{$tenant->tenant_id}");

            // In a real environment, we would run mysqldump:
            // $command = sprintf(
            //     'mysqldump -u%s -p%s -h%s %s > %s',
            //     config('tenant.database.username'),
            //     config('tenant.database.password'),
            //     config('tenant.database.host'),
            //     $dbName,
            //     storage_path("app/{$filename}")
            // );
            // exec($command);

            // For simulation/demonstration:
            $backupPath = storage_path("app/{$filename}");
            File::put($backupPath, "-- Simulation Backup for {$dbName}\n-- Date: " . now()->toDateTimeString() . "\n-- Context: {$context}\n");

            Log::info("Backup completed: {$filename}");
            
            return $filename;
        } catch (\Exception $e) {
            Log::error("Backup failed for tenant {$tenant->tenant_id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Auto-backup before a tenant is deleted or a critical action occurs.
     */
    public function backupBeforeCriticalAction(Tenant $tenant, string $action): void
    {
        $this->backup($tenant, "pre_{$action}");
    }
}
