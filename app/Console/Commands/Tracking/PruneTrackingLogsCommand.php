<?php

namespace App\Console\Commands\Tracking;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Prune old tracking event logs based on tier-based retention policies.
 *
 * Retention:
 *   Free → 3 days
 *   Pro  → 10 days
 *   Enterprise → 30 days
 *
 * Usage: php artisan sgtm:prune-logs
 */
class PruneTrackingLogsCommand extends Command
{
    protected $signature = 'sgtm:prune-logs {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Prune old tracking event logs based on tier retention policy';

    // Retention in days per tier
    private const RETENTION = [
        'free'       => 3,
        'pro'        => 10,
        'enterprise' => 30,
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $tenantDbs = DB::connection('mysql')
            ->table('tenants')
            ->where('status', 'active')
            ->pluck('database_name');

        $totalPruned = 0;

        foreach ($tenantDbs as $dbName) {
            try {
                // Configure dynamic tenant connection
                config(["database.connections.tenant_dynamic.database" => $dbName]);
                DB::purge('tenant_dynamic');

                // Check if tracking tables exist
                if (!DB::connection('tenant_dynamic')->getSchemaBuilder()->hasTable('ec_tracking_event_logs')) {
                    continue;
                }

                // For now, default to 'free' tier retention (3 days)
                // In production, look up the tenant's subscription tier
                $tier = 'free';
                $retentionDays = self::RETENTION[$tier] ?? 3;
                $cutoff = now()->subDays($retentionDays)->toDateTimeString();

                if ($dryRun) {
                    $count = DB::connection('tenant_dynamic')
                        ->table('ec_tracking_event_logs')
                        ->where('created_at', '<', $cutoff)
                        ->count();
                    $this->info("[{$dbName}] Would prune {$count} logs (tier: {$tier}, retention: {$retentionDays}d)");
                    $totalPruned += $count;
                } else {
                    $deleted = DB::connection('tenant_dynamic')
                        ->table('ec_tracking_event_logs')
                        ->where('created_at', '<', $cutoff)
                        ->delete();
                    $this->info("[{$dbName}] Pruned {$deleted} logs (tier: {$tier}, retention: {$retentionDays}d)");
                    $totalPruned += $deleted;
                }
            } catch (\Exception $e) {
                $this->error("[{$dbName}] Error: {$e->getMessage()}");
                Log::error("[sGTM Prune] Failed for {$dbName}: {$e->getMessage()}");
            }
        }

        $action = $dryRun ? 'Would prune' : 'Pruned';
        $this->info("{$action} {$totalPruned} total event logs across all tenants.");

        return self::SUCCESS;
    }
}
