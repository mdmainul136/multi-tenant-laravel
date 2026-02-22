<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupProvisioning extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:cleanup-provisioning {--hours=24 : Cleanup tenants older than X hours}';

    protected $description = 'Clean up failed or stalled tenant provisioning attempts';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\TenantService $tenantService): int
    {
        $hours = (int) $this->option('hours');
        $this->info("Cleaning up provisioning attempts older than {$hours} hours...");

        $count = $tenantService->cleanupFailedProvisioning($hours);

        $this->info("Successfully cleaned up {$count} failed tenant(s).");

        return 0;
    }
}
