<?php

namespace App\Console\Commands\Tracking;

use App\Models\Tracking\TrackingContainer;
use App\Modules\Tracking\Services\DockerOrchestratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Background monitor for sGTM containers.
 * 
 * This command:
 * 1. Iterates through all active TrackingContainers.
 * 2. Checks their Docker status via the Orchestrator.
 * 3. Restarts any container that should be 'running' but is not.
 * 4. Logs incidents for audit trails.
 * 
 * Schedule in app/Console/Kernel.php:
 *   $schedule->command('sgtm:monitor')->everyFiveMinutes();
 */
class SgtmMonitorCommand extends Command
{
    protected $signature = 'sgtm:monitor';
    protected $description = 'Monitor and auto-heal sGTM Docker containers';

    public function handle(DockerOrchestratorService $orchestrator): int
    {
        $this->info("🔍 Starting sGTM Health Monitor...");

        // Only monitor containers that are marked as active and have been provisioned
        $containers = TrackingContainer::where('is_active', true)
            ->whereNotNull('docker_container_id')
            ->get();

        if ($containers->isEmpty()) {
            $this->info("No active sGTM containers to monitor.");
            return 0;
        }

        $headers = ['Container ID', 'Status', 'Health', 'Action Taken'];
        $results = [];

        foreach ($containers as $container) {
            $health = $orchestrator->healthCheck($container);
            $status = $health['docker_status'] ?? 'unknown';
            $action = 'None';

            // If it's not running but should be, try to heal it
            if ($status !== 'running') {
                $this->warn("⚠️ Container {$container->container_id} is {$status}. Attempting to heal...");
                
                try {
                    // Try to start it first
                    $orchestrator->deploy($container, $container->domain);
                    $action = 'Restarted/Redeployed';
                    Log::info("[sGTM Monitor] Auto-healed container: {$container->container_id}");
                } catch (\Exception $e) {
                    $action = 'Heal Failed: ' . $e->getMessage();
                    Log::error("[sGTM Monitor] Failed to heal container {$container->container_id}: " . $e->getMessage());
                }
            }

            $results[] = [
                $container->container_id,
                $status,
                ($status === 'running') ? '✅' : '❌',
                $action
            ];
        }

        $this->table($headers, $results);
        $this->info("✅ Monitor check complete.");

        return 0;
    }
}
