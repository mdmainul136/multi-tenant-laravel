<?php

namespace App\Modules\Tracking\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Channel Health Dashboard Service.
 *
 * Tracks per-channel delivery metrics:
 *   - Success / failure rates per day per channel
 *   - Average and P99 latency
 *   - Error breakdown by type
 *   - Trend analysis and alerts
 */
class ChannelHealthService
{
    private const TABLE = 'ec_tracking_channel_health';

    /**
     * Record a delivery attempt result.
     */
    public function recordAttempt(int $containerId, string $channel, bool $success, float $latencyMs, ?string $errorType = null): void
    {
        $date = Carbon::today()->toDateString();

        // Upsert daily record
        $existing = DB::table(self::TABLE)
            ->where('container_id', $containerId)
            ->where('channel', $channel)
            ->where('date', $date)
            ->first();

        if ($existing) {
            $updates = [
                'events_sent'      => DB::raw('events_sent + 1'),
                'updated_at'       => now(),
            ];

            if ($success) {
                $updates['events_succeeded'] = DB::raw('events_succeeded + 1');
            } else {
                $updates['events_failed'] = DB::raw('events_failed + 1');
            }

            // Running average latency
            $newAvg = (($existing->avg_latency_ms * $existing->events_sent) + $latencyMs) / ($existing->events_sent + 1);
            $updates['avg_latency_ms'] = round($newAvg, 2);

            // P99 (keep max)
            if ($latencyMs > $existing->p99_latency_ms) {
                $updates['p99_latency_ms'] = round($latencyMs, 2);
            }

            // Error breakdown
            if ($errorType) {
                $breakdown = json_decode($existing->error_breakdown ?? '{}', true);
                $breakdown[$errorType] = ($breakdown[$errorType] ?? 0) + 1;
                $updates['error_breakdown'] = json_encode($breakdown);
            }

            DB::table(self::TABLE)
                ->where('id', $existing->id)
                ->update($updates);
        } else {
            DB::table(self::TABLE)->insert([
                'container_id'     => $containerId,
                'channel'          => $channel,
                'date'             => $date,
                'events_sent'      => 1,
                'events_succeeded' => $success ? 1 : 0,
                'events_failed'    => $success ? 0 : 1,
                'events_retried'   => 0,
                'avg_latency_ms'   => round($latencyMs, 2),
                'p99_latency_ms'   => round($latencyMs, 2),
                'error_breakdown'  => $errorType ? json_encode([$errorType => 1]) : null,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    /**
     * Get health dashboard for a container.
     */
    public function getDashboard(int $containerId, int $days = 7): array
    {
        $since = Carbon::today()->subDays($days);

        $records = DB::table(self::TABLE)
            ->where('container_id', $containerId)
            ->where('date', '>=', $since->toDateString())
            ->orderBy('date', 'desc')
            ->get();

        // Aggregate by channel
        $channels = [];
        foreach ($records as $row) {
            $channel = $row->channel;
            if (!isset($channels[$channel])) {
                $channels[$channel] = [
                    'channel'         => $channel,
                    'total_sent'      => 0,
                    'total_succeeded' => 0,
                    'total_failed'    => 0,
                    'total_retried'   => 0,
                    'avg_latency_ms'  => 0,
                    'max_latency_ms'  => 0,
                    'success_rate'    => 0,
                    'daily'           => [],
                    'errors'          => [],
                ];
            }

            $c = &$channels[$channel];
            $c['total_sent']      += $row->events_sent;
            $c['total_succeeded'] += $row->events_succeeded;
            $c['total_failed']    += $row->events_failed;
            $c['total_retried']   += $row->events_retried;
            $c['max_latency_ms']  = max($c['max_latency_ms'], $row->p99_latency_ms);

            $c['daily'][] = [
                'date'      => $row->date,
                'sent'      => $row->events_sent,
                'succeeded' => $row->events_succeeded,
                'failed'    => $row->events_failed,
                'latency'   => $row->avg_latency_ms,
            ];

            // Merge error breakdowns
            $errors = json_decode($row->error_breakdown ?? '{}', true);
            foreach ($errors as $type => $count) {
                $c['errors'][$type] = ($c['errors'][$type] ?? 0) + $count;
            }
        }

        // Calculate aggregate rates
        foreach ($channels as &$c) {
            $c['success_rate'] = $c['total_sent'] > 0
                ? round($c['total_succeeded'] / $c['total_sent'] * 100, 1)
                : 0;
            $c['avg_latency_ms'] = $c['total_sent'] > 0
                ? round(collect($c['daily'])->avg('latency'), 2)
                : 0;

            $c['status'] = match (true) {
                $c['success_rate'] >= 99 => 'healthy',
                $c['success_rate'] >= 95 => 'degraded',
                $c['success_rate'] >= 80 => 'warning',
                default                  => 'critical',
            };
        }

        return [
            'container_id' => $containerId,
            'period_days'  => $days,
            'channels'     => array_values($channels),
            'overall'      => [
                'total_sent'      => array_sum(array_column($channels, 'total_sent')),
                'total_succeeded' => array_sum(array_column($channels, 'total_succeeded')),
                'total_failed'    => array_sum(array_column($channels, 'total_failed')),
            ],
        ];
    }

    /**
     * Get channels that need attention (degraded or worse).
     */
    public function getAlerts(int $containerId): array
    {
        $dashboard = $this->getDashboard($containerId, 1);
        $alerts = [];

        foreach ($dashboard['channels'] as $channel) {
            if (in_array($channel['status'], ['degraded', 'warning', 'critical'])) {
                $alerts[] = [
                    'channel'      => $channel['channel'],
                    'status'       => $channel['status'],
                    'success_rate' => $channel['success_rate'],
                    'failed_today' => $channel['total_failed'],
                    'top_error'    => !empty($channel['errors'])
                        ? array_key_first(arsort($channel['errors']) ?: $channel['errors'])
                        : 'unknown',
                ];
            }
        }

        return $alerts;
    }
}
