<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\DatabaseManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Tenant $tenant,
        protected string $adminPassword
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DatabaseManager $databaseManager, \App\Services\ModuleService $moduleService): void
    {
        $tenantId = $this->tenant->tenant_id;
        $dbName = $this->tenant->database_name;

        try {
            Log::info("Starting provisioning for tenant: {$tenantId}");

            // 1. Create Database
            $this->tenant->update(['provisioning_status' => 'creating_db']);
            $databaseManager->createTenantDatabase($dbName);

            // 2. Run Migrations
            $this->tenant->update(['provisioning_status' => 'migrating']);
            $databaseManager->runTenantMigrations($dbName);

            // 3. Create Admin User
            $this->tenant->update(['provisioning_status' => 'creating_admin']);
            $databaseManager->createAdminUser(
                $dbName,
                $this->tenant->admin_email,
                $this->adminPassword
            );

            // 4. Auto-Activate Modules based on Business Type + Region
            $this->tenant->update(['provisioning_status' => 'activating_modules']);
            $starterModules = $moduleService->getModulesForOnboarding($this->tenant);

            Log::info("Auto-activating modules for tenant {$tenantId}: " . implode(', ', $starterModules));

            foreach ($starterModules as $module) {
                try {
                    $moduleService->subscribeModule($this->tenant->id, $module, [
                        'subscription_type' => 'trial',
                        'expires_at'        => now()->addDays(14),
                        'status'            => 'active'
                    ]);
                } catch (\Exception $e) {
                    Log::warning("Could not activate module {$module} for tenant {$tenantId}: " . $e->getMessage());
                }
            }
            // 5. Finalize
            $this->tenant->update([
                'provisioning_status' => 'completed',
                'status' => 'active'
            ]);

            // Register subdomain in tenant_domains
            TenantDomain::updateOrCreate(
                ['tenant_id' => $tenantId, 'domain' => $this->tenant->domain],
                [
                    'is_primary' => true,
                    'is_verified' => true,
                    'status' => 'verified',
                ]
            );

            // 6. Send Welcome Email
            try {
                \Illuminate\Support\Facades\Mail::to($this->tenant->admin_email)
                    ->send(new \App\Mail\TenantWelcome($this->tenant));
            } catch (\Exception $e) {
                Log::warning("Could not send welcome email to {$this->tenant->admin_email}: " . $e->getMessage());
            }

            Log::info("Provisioning completed for tenant: {$tenantId}");

        } catch (\Exception $e) {
            Log::error("Provisioning failed for tenant {$tenantId}: " . $e->getMessage());
            $this->tenant->update(['provisioning_status' => 'failed']);
            throw $e; // Release back to queue for retry
        }
    }
}
