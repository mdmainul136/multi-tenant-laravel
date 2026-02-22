<?php

namespace App\Modules\Tracking\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Retry & Dead Letter Queue Service.
 *
 * Handles:
 *   - Enqueuing failed events for retry
 *   - Exponential backoff scheduling (1m, 5m, 15m, 60m, 240m)
 *   - Processing retry queue via scheduled command
 *   - Dead letter expiration (events that exceed max_attempts)
 *   - Retry statistics and monitoring
 */
class RetryQueueService
{
    private const TABLE = 'ec_tracking_dlq';

    /**
     * Backoff schedule: attempt → delay in minutes.
     */
    private const BACKOFF_MINUTES = [
        1 => 1,     // 1 minute
        2 => 5,     // 5 minutes
        3 => 15,    // 15 minutes
        4 => 60,    // 1 hour
        5 => 240,   // 4 hours
    ];

    /**
     * Enqueue a failed event for retry.
     */
    public function enqueue(array $params): int
    {
        $attempt = ($params['attempt_count'] ?? 0) + 1;
        $maxAttempts = $params['max_attempts'] ?? 5;

        $status = $attempt > $maxAttempts ? 'failed' : 'pending';
        $nextRetry = $status === 'pending'
            ? Carbon::now()->addMinutes(self::BACKOFF_MINUTES[$attempt] ?? 240)
            : null;

        return DB::table(self::TABLE)->insertGetId([
            'container_id'     => $params['container_id'],
            'destination_id'   => $params['destination_id'] ?? null,
            'destination_type' => $params['destination_type'],
            'event_name'       => $params['event_name'] ?? 'unknown',
            'event_id'         => $params['event_id'] ?? null,
            'event_payload'    => json_encode($params['event_payload']),
            'credentials'      => isset($params['credentials']) ? encrypt(json_encode($params['credentials'])) : null,
            'error_message'    => substr($params['error_message'] ?? '', 0, 500),
            'attempt_count'    => $attempt,
            'max_attempts'     => $maxAttempts,
            'next_retry_at'    => $nextRetry,
            'last_attempted_at' => now(),
            'status'           => $status,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /**
     * Get events ready for retry.
     */
    public function getRetryBatch(int $limit = 50): array
    {
        return DB::table(self::TABLE)
            ->where('status', 'pending')
            ->where('next_retry_at', '<=', now())
            ->orderBy('next_retry_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id'               => $row->id,
                'container_id'     => $row->container_id,
                'destination_id'   => $row->destination_id,
                'destination_type' => $row->destination_type,
                'event_name'       => $row->event_name,
                'event_id'         => $row->event_id,
                'event_payload'    => json_decode($row->event_payload, true),
                'credentials'      => $row->credentials ? json_decode(decrypt($row->credentials), true) : null,
                'attempt_count'    => $row->attempt_count,
                'max_attempts'     => $row->max_attempts,
            ])
            ->toArray();
    }

    /**
     * Mark a DLQ entry as retrying.
     */
    public function markRetrying(int $id): void
    {
        DB::table(self::TABLE)->where('id', $id)->update([
            'status'     => 'retrying',
            'updated_at' => now(),
        ]);
    }

    /**
     * Mark a DLQ entry as succeeded.
     */
    public function markSucceeded(int $id): void
    {
        DB::table(self::TABLE)->where('id', $id)->update([
            'status'     => 'succeeded',
            'updated_at' => now(),
        ]);
    }

    /**
     * Re-enqueue a failed retry with incremented attempt count.
     */
    public function reEnqueue(int $id, string $error): void
    {
        $entry = DB::table(self::TABLE)->find($id);
        if (!$entry) return;

        $nextAttempt = $entry->attempt_count + 1;

        if ($nextAttempt > $entry->max_attempts) {
            DB::table(self::TABLE)->where('id', $id)->update([
                'status'            => 'failed',
                'error_message'     => substr($error, 0, 500),
                'attempt_count'     => $nextAttempt,
                'last_attempted_at' => now(),
                'updated_at'        => now(),
            ]);
            return;
        }

        $backoff = self::BACKOFF_MINUTES[$nextAttempt] ?? 240;

        DB::table(self::TABLE)->where('id', $id)->update([
            'status'            => 'pending',
            'error_message'     => substr($error, 0, 500),
            'attempt_count'     => $nextAttempt,
            'next_retry_at'     => Carbon::now()->addMinutes($backoff),
            'last_attempted_at' => now(),
            'updated_at'        => now(),
        ]);
    }

    /**
     * Expire old failed entries (older than 7 days).
     */
    public function expireOldEntries(int $days = 7): int
    {
        return DB::table(self::TABLE)
            ->whereIn('status', ['failed', 'succeeded'])
            ->where('updated_at', '<', Carbon::now()->subDays($days))
            ->update(['status' => 'expired']);
    }

    /**
     * Get DLQ statistics for a container.
     */
    public function getStats(?int $containerId = null): array
    {
        $query = DB::table(self::TABLE);
        if ($containerId) {
            $query->where('container_id', $containerId);
        }

        $stats = $query->select(
            DB::raw("status, COUNT(*) as count"),
        )->groupBy('status')->pluck('count', 'status')->toArray();

        $byChannel = DB::table(self::TABLE)
            ->when($containerId, fn ($q) => $q->where('container_id', $containerId))
            ->where('status', 'pending')
            ->select('destination_type', DB::raw('COUNT(*) as count'))
            ->groupBy('destination_type')
            ->pluck('count', 'destination_type')
            ->toArray();

        return [
            'total_pending'   => $stats['pending'] ?? 0,
            'total_retrying'  => $stats['retrying'] ?? 0,
            'total_succeeded' => $stats['succeeded'] ?? 0,
            'total_failed'    => $stats['failed'] ?? 0,
            'pending_by_channel' => $byChannel,
        ];
    }

    /**
     * Purge all succeeded/expired entries.
     */
    public function purge(): int
    {
        return DB::table(self::TABLE)
            ->whereIn('status', ['succeeded', 'expired'])
            ->delete();
    }
}
