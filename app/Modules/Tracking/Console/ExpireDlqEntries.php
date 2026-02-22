<?php

namespace App\Modules\Tracking\Console;

use App\Modules\Tracking\Services\RetryQueueService;
use Illuminate\Console\Command;

/**
 * Expire old DLQ entries and optionally purge succeeded/expired records.
 *
 * Schedule: Daily at 2:00 AM.
 *
 * Usage:
 *   php artisan tracking:expire-dlq
 *   php artisan tracking:expire-dlq --days=14
 *   php artisan tracking:expire-dlq --purge
 */
class ExpireDlqEntries extends Command
{
    protected $signature = 'tracking:expire-dlq
        {--days=7 : Mark entries older than N days as expired}
        {--purge : Also delete succeeded and expired entries}';

    protected $description = 'Expire old DLQ entries and optionally purge completed records';

    public function __construct(private RetryQueueService $retryQueue)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $days  = (int) $this->option('days');
        $purge = (bool) $this->option('purge');

        $this->info("🕐 Expiring DLQ entries older than {$days} days...");

        $expired = $this->retryQueue->expireOldEntries($days);
        $this->info("  📦 Expired: {$expired} entries");

        if ($purge) {
            $this->info('🗑️  Purging succeeded and expired entries...');
            $purged = $this->retryQueue->purge();
            $this->info("  🧹 Purged: {$purged} entries");
        }

        // Show current stats
        $stats = $this->retryQueue->getStats();
        $this->newLine();
        $this->table(
            ['Status', 'Count'],
            [
                ['Pending',   $stats['total_pending']],
                ['Retrying',  $stats['total_retrying']],
                ['Succeeded', $stats['total_succeeded']],
                ['Failed',    $stats['total_failed']],
            ]
        );

        return self::SUCCESS;
    }
}
