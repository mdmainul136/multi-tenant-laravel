<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\DatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class TenantsMigrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:migrate {--tenant= : Run for a specific tenant ID} {--fresh : Wipe and re-migrate} {--seed : Seed after migration} {--path= : Custom migration path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations for all tenant databases or a specific one';

    protected DatabaseManager $databaseManager;

    /**
     * Create a new command instance.
     */
    public function __construct(DatabaseManager $databaseManager)
    {
        parent::__construct();
        $this->databaseManager = $databaseManager;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->option('tenant');
        
        if ($tenantId) {
            $tenants = Tenant::where('tenant_id', $tenantId)->get();
            if ($tenants->isEmpty()) {
                $this->error("Tenant '{$tenantId}' not found.");
                return 1;
            }
        } else {
            $tenants = Tenant::where('status', 'active')->get();
        }

        if ($tenants->isEmpty()) {
            $this->info('No active tenants found to migrate.');
            return 0;
        }

        $this->info("Found {$tenants->count()} tenant(s). Starting migrations...");

        // Define migration paths
        $paths = [
            'database/migrations/tenant'
        ];

        // Add module migrations if they exist
        $modulePath = base_path('database/migrations/tenant/modules');
        if (is_dir($modulePath)) {
            $modules = array_filter(glob($modulePath . '/*'), 'is_dir');
            foreach ($modules as $module) {
                $paths[] = 'database/migrations/tenant/modules/' . basename($module);
            }
        }

        if ($this->option('path')) {
            $paths = [$this->option('path')];
        }

        foreach ($tenants as $tenant) {
            $this->line("\n=========================================");
            $this->info("Migrating Tenant: {$tenant->tenant_name} ({$tenant->tenant_id})");
            $this->line("Database: {$tenant->database_name}");
            $this->line("=========================================");

            try {
                // Switch default connection to this tenant's dynamic connection
                $this->databaseManager->switchToTenantDatabase($tenant->tenant_id);

                $command = $this->option('fresh') ? 'migrate:fresh' : 'migrate';
                
                $exitCode = Artisan::call($command, [
                    '--database' => 'tenant_dynamic',
                    '--path' => $paths,
                    '--force' => true,
                ]);

                $this->info(Artisan::output());

                if ($exitCode === 0) {
                    $this->info("✓ Successfully migrated {$tenant->tenant_id}");
                    
                    if ($this->option('seed')) {
                        $this->info("Seeding tenant '{$tenant->tenant_id}'...");
                        Artisan::call('db:seed', [
                            '--database' => 'tenant_dynamic',
                            '--class' => 'Database\Seeders\TenantDatabaseSeeder',
                            '--force' => true,
                        ]);
                        $this->info(Artisan::output());
                    }
                } else {
                    $this->error("✗ Migration failed for {$tenant->tenant_id} with exit code {$exitCode}");
                }

            } catch (\Exception $e) {
                $this->error("✗ Error migrating {$tenant->tenant_id}: " . $e->getMessage());
                Log::error("Tenant migration error ({$tenant->tenant_id}): " . $e->getMessage());
            }
        }

        $this->line("\n-----------------------------------------");
        $this->info("All requested tenant migrations completed.");
        $this->line("-----------------------------------------");

        return 0;
    }
}

