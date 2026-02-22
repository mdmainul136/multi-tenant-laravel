<?php

namespace App\Modules\Tracking\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Purge expired consent records.
 *
 * Removes consent records that have passed their expiration date.
 * This ensures GDPR compliance by not retaining stale consent data
 * beyond the configured expiry period (default 13 months).
 *
 * Schedule: Daily at 3:00 AM.
 *
 * Usage:
 *   php artisan tracking:purge-consent
 *   php artisan tracking:purge-consent --dry-run
 */
class PurgeExpiredConsent extends Command
{
    protected $signature = 'tracking:purge-consent
        {--dry-run : Preview what would be purged without deleting}';

    protected $description = 'Purge expired consent records for GDPR compliance';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now    = Carbon::now();

        $this->info('🔒 Checking for expired consent records...');

        $expiredCount = DB::table('ec_tracking_consent')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->count();

        if ($expiredCount === 0) {
            $this->info('✅ No expired consent records found.');
            return self::SUCCESS;
        }

        $this->info("  📦 Found {$expiredCount} expired consent record(s).");

        if ($dryRun) {
            // Show sample of what would be purged
            $sample = DB::table('ec_tracking_consent')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $now)
                ->select('id', 'container_id', 'visitor_id', 'expires_at', 'consent_source')
                ->limit(20)
                ->get();

            $this->table(
                ['ID', 'Container', 'Visitor', 'Expired At', 'Source'],
                $sample->map(fn ($r) => [$r->id, $r->container_id, substr($r->visitor_id, 0, 20) . '...', $r->expires_at, $r->consent_source])->toArray()
            );

            $this->warn('🏷️  Dry run — no records were deleted.');
            return self::SUCCESS;
        }

        // Purge in batches to avoid memory issues
        $totalDeleted = 0;
        $batchSize    = 500;

        do {
            $deleted = DB::table('ec_tracking_consent')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $now)
                ->limit($batchSize)
                ->delete();

            $totalDeleted += $deleted;

            if ($deleted > 0) {
                $this->line("  🧹 Deleted batch: {$deleted}");
            }
        } while ($deleted >= $batchSize);

        $this->newLine();
        $this->info("✅ Purged {$totalDeleted} expired consent records.");

        // Show remaining stats
        $remaining = DB::table('ec_tracking_consent')->count();
        $this->line("  📊 Active consent records remaining: {$remaining}");

        return self::SUCCESS;
    }
}
