<?php

namespace App\Modules\Tracking\Console;

use App\Modules\Tracking\Services\RetryQueueService;
use App\Modules\Tracking\Services\DestinationService;
use App\Modules\Tracking\Services\ChannelHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Process the tracking DLQ retry queue.
 *
 * Fetches pending events, re-sends them to their destinations,
 * and either marks them succeeded or re-enqueues with incremented backoff.
 *
 * Schedule: Every minute (picks up events whose next_retry_at has elapsed).
 *
 * Usage:
 *   php artisan tracking:process-retry-queue
 *   php artisan tracking:process-retry-queue --batch=100
 *   php artisan tracking:process-retry-queue --dry-run
 */
class ProcessRetryQueue extends Command
{
    protected $signature = 'tracking:process-retry-queue
        {--batch=50 : Maximum events to process per run}
        {--dry-run : Preview what would be retried without sending}';

    protected $description = 'Process the tracking Dead Letter Queue and retry failed event deliveries';

    public function __construct(
        private RetryQueueService $retryQueue,
        private DestinationService $destinations,
        private ChannelHealthService $health,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');
        $dryRun    = (bool) $this->option('dry-run');

        $this->info("📡 Fetching up to {$batchSize} events from DLQ...");

        $batch = $this->retryQueue->getRetryBatch($batchSize);

        if (empty($batch)) {
            $this->info('✅ No events pending retry.');
            return self::SUCCESS;
        }

        $this->info(sprintf('🔄 Found %d events to retry.', count($batch)));

        if ($dryRun) {
            $this->table(
                ['ID', 'Type', 'Event', 'Attempt', 'Max'],
                array_map(fn ($e) => [$e['id'], $e['destination_type'], $e['event_name'], $e['attempt_count'], $e['max_attempts']], $batch)
            );
            $this->warn('🏷️  Dry run — no events were sent.');
            return self::SUCCESS;
        }

        $succeeded = 0;
        $failed    = 0;

        foreach ($batch as $entry) {
            $this->retryQueue->markRetrying($entry['id']);

            $startTime = microtime(true);

            try {
                $result = $this->destinations->send(
                    $entry['destination_type'],
                    $entry['event_payload'],
                    $entry['credentials'] ?? [],
                );

                $latencyMs = (microtime(true) - $startTime) * 1000;

                if (!empty($result['success'])) {
                    $this->retryQueue->markSucceeded($entry['id']);
                    $this->health->recordAttempt(
                        $entry['container_id'], $entry['destination_type'], true, $latencyMs
                    );
                    $succeeded++;
                    $this->line("  ✅ #{$entry['id']} → {$entry['destination_type']} (attempt {$entry['attempt_count']})");
                } else {
                    $error = $result['error'] ?? 'Unknown error';
                    $this->retryQueue->reEnqueue($entry['id'], $error);
                    $this->health->recordAttempt(
                        $entry['container_id'], $entry['destination_type'], false, $latencyMs, 'api_error'
                    );
                    $failed++;
                    $this->line("  ❌ #{$entry['id']} → {$entry['destination_type']}: {$error}");
                }
            } catch (\Exception $e) {
                $latencyMs = (microtime(true) - $startTime) * 1000;
                $this->retryQueue->reEnqueue($entry['id'], $e->getMessage());
                $this->health->recordAttempt(
                    $entry['container_id'], $entry['destination_type'], false, $latencyMs, 'exception'
                );
                $failed++;
                Log::error('[DLQ Retry] Exception', [
                    'dlq_id' => $entry['id'],
                    'error'  => $e->getMessage(),
                ]);
                $this->line("  💥 #{$entry['id']} → {$entry['destination_type']}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("📊 Results: {$succeeded} succeeded, {$failed} failed out of " . count($batch) . " total.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
