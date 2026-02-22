<?php

namespace App\Modules\Tracking\Console;

use App\Modules\Tracking\Services\ChannelHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate and report channel health metrics.
 *
 * Reads from ec_tracking_channel_health and produces a summary report.
 * Can generate alerts for degraded channels.
 *
 * Schedule: Every 15 minutes.
 *
 * Usage:
 *   php artisan tracking:health-report
 *   php artisan tracking:health-report --days=1
 *   php artisan tracking:health-report --container=5
 *   php artisan tracking:health-report --alert-only
 */
class AggregateHealthMetrics extends Command
{
    protected $signature = 'tracking:health-report
        {--days=1 : Number of days to aggregate}
        {--container= : Specific container ID to report on}
        {--alert-only : Only show channels with issues}';

    protected $description = 'Generate channel health report and alerts for tracking destinations';

    public function __construct(private ChannelHealthService $health)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $days        = (int) $this->option('days');
        $containerId = $this->option('container');
        $alertOnly   = (bool) $this->option('alert-only');

        // Get all containers with health data, or just the specified one
        $containerIds = $containerId
            ? [(int) $containerId]
            : DB::table('ec_tracking_channel_health')
                ->distinct()
                ->pluck('container_id')
                ->toArray();

        if (empty($containerIds)) {
            $this->info('📭 No channel health data found.');
            return self::SUCCESS;
        }

        $this->info("📊 Channel Health Report ({$days}-day window)");
        $this->info(str_repeat('═', 60));

        $totalAlerts = 0;

        foreach ($containerIds as $cId) {
            $dashboard = $this->health->getDashboard($cId, $days);
            $alerts    = $this->health->getAlerts($cId);

            if ($alertOnly && empty($alerts)) continue;

            $this->newLine();
            $this->info("🏗️  Container #{$cId}");

            if (empty($dashboard['channels'])) {
                $this->line('  No data.');
                continue;
            }

            // Channel table
            $rows = [];
            foreach ($dashboard['channels'] as $ch) {
                if ($alertOnly && $ch['status'] === 'healthy') continue;

                $statusIcon = match ($ch['status']) {
                    'healthy'  => '🟢',
                    'degraded' => '🟡',
                    'warning'  => '🟠',
                    'critical' => '🔴',
                    default    => '⚪',
                };

                $rows[] = [
                    "{$statusIcon} {$ch['channel']}",
                    $ch['total_sent'],
                    $ch['total_succeeded'],
                    $ch['total_failed'],
                    "{$ch['success_rate']}%",
                    round($ch['avg_latency_ms']) . 'ms',
                    round($ch['max_latency_ms']) . 'ms',
                    $ch['status'],
                ];
            }

            if (!empty($rows)) {
                $this->table(
                    ['Channel', 'Sent', 'OK', 'Fail', 'Rate', 'Avg Lat', 'P99 Lat', 'Status'],
                    $rows
                );
            }

            // Alerts
            if (!empty($alerts)) {
                $totalAlerts += count($alerts);
                $this->warn('  ⚠️  Alerts:');
                foreach ($alerts as $alert) {
                    $this->line("    → {$alert['channel']}: {$alert['status']} ({$alert['success_rate']}% success, {$alert['failed_today']} failures)");
                }
            }

            // Overall
            $this->line(sprintf(
                '  📈 Total: %d sent, %d succeeded, %d failed',
                $dashboard['overall']['total_sent'],
                $dashboard['overall']['total_succeeded'],
                $dashboard['overall']['total_failed'],
            ));
        }

        $this->newLine();
        if ($totalAlerts > 0) {
            $this->error("🚨 {$totalAlerts} alert(s) detected across all containers.");
            return self::FAILURE;
        }

        $this->info('✅ All channels healthy.');
        return self::SUCCESS;
    }
}
