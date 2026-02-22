<?php

namespace App\Modules\Tracking\Services;

use Illuminate\Support\Facades\DB;

/**
 * Tracking Analytics Service.
 *
 * Provides insights similar to Stape Analytics:
 *   - Data recovery percentage (events captured via server-side vs client-side)
 *   - Ad blocker bypass rate
 *   - Destination delivery success rates
 *   - Tracking Health Score (0-100)
 */
class TrackingAnalyticsService
{
    /**
     * Generate a comprehensive analytics report for a container.
     */
    public function getAnalytics(int $containerId, ?string $from = null, ?string $to = null): array
    {
        $from = $from ?? now()->startOfMonth()->toDateString();
        $to   = $to ?? now()->toDateString();

        $usage = $this->getUsageData($containerId, $from, $to);

        return [
            'period'            => ['from' => $from, 'to' => $to],
            'total_events'      => $usage['total_received'],
            'events_forwarded'  => $usage['total_forwarded'],
            'events_dropped'    => $usage['total_dropped'],
            'events_errors'     => $usage['total_errors'],
            'data_recovery'     => $this->calculateDataRecovery($usage),
            'delivery_rate'     => $this->calculateDeliveryRate($usage),
            'health_score'      => $this->calculateHealthScore($usage),
            'ad_blocker_impact' => $this->estimateAdBlockerImpact($usage),
            'daily_breakdown'   => $usage['daily'],
        ];
    }

    /**
     * Get raw usage data from the tracking_usage table.
     */
    private function getUsageData(int $containerId, string $from, string $to): array
    {
        $daily = DB::connection('tenant_dynamic')->table('ec_tracking_usage')
            ->where('container_id', $containerId)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();

        $totals = [
            'total_received'  => $daily->sum('events_received'),
            'total_forwarded' => $daily->sum('events_forwarded'),
            'total_dropped'   => $daily->sum('events_dropped'),
            'total_errors'    => $daily->sum('events_errors'),
            'daily'           => $daily->toArray(),
        ];

        return $totals;
    }

    /**
     * Calculate data recovery rate.
     * Measures: How many events were successfully captured server-side
     * that would have been lost with client-side only tracking.
     *
     * Industry average: ad blockers affect 25-40% of pageviews.
     * If we're receiving events > estimated client-side, that's recovery.
     */
    private function calculateDataRecovery(array $usage): array
    {
        $total = $usage['total_received'];
        if ($total === 0) {
            return ['percentage' => 0, 'events_recovered' => 0, 'status' => 'no_data'];
        }

        // Estimate: ~30% of traffic uses ad blockers
        $estimatedClientOnly = (int) ($total * 0.70);
        $recovered = max(0, $total - $estimatedClientOnly);

        return [
            'percentage'       => round(($recovered / $total) * 100, 1),
            'events_recovered' => $recovered,
            'estimated_blocked' => $total - $estimatedClientOnly,
            'status'           => $recovered > 0 ? 'active' : 'minimal',
        ];
    }

    /**
     * Calculate destination delivery success rate.
     */
    private function calculateDeliveryRate(array $usage): array
    {
        $forwarded = $usage['total_forwarded'];
        $errors = $usage['total_errors'];
        $total = $forwarded + $errors;

        if ($total === 0) {
            return ['percentage' => 100, 'status' => 'no_data'];
        }

        $rate = round(($forwarded / $total) * 100, 1);

        return [
            'percentage' => $rate,
            'successful' => $forwarded,
            'failed'     => $errors,
            'status'     => $rate >= 99 ? 'excellent' : ($rate >= 95 ? 'good' : ($rate >= 90 ? 'fair' : 'poor')),
        ];
    }

    /**
     * Calculate overall tracking health score (0-100).
     *
     * Factors:
     *   - Delivery rate (40%)
     *   - Data volume consistency (20%)
     *   - Error rate (20%)
     *   - Drop rate (20%)
     */
    private function calculateHealthScore(array $usage): array
    {
        $total = $usage['total_received'];
        if ($total === 0) {
            return ['score' => 0, 'grade' => 'N/A', 'factors' => []];
        }

        // Delivery factor (0-40)
        $forwarded = $usage['total_forwarded'];
        $errors = $usage['total_errors'];
        $deliveryScore = ($forwarded + $errors) > 0
            ? ($forwarded / ($forwarded + $errors)) * 40
            : 40;

        // Error rate factor (0-20): lower is better
        $errorRate = $total > 0 ? ($errors / $total) : 0;
        $errorScore = max(0, 20 - ($errorRate * 200));

        // Drop rate factor (0-20): lower is better
        $dropRate = $total > 0 ? ($usage['total_dropped'] / $total) : 0;
        $dropScore = max(0, 20 - ($dropRate * 200));

        // Consistency factor (0-20): check daily variance
        $dailyCounts = collect($usage['daily'])->pluck('events_received')->filter();
        $consistencyScore = 20;
        if ($dailyCounts->count() > 1) {
            $avg = $dailyCounts->average();
            $stdDev = sqrt($dailyCounts->map(fn ($v) => pow($v - $avg, 2))->average());
            $cv = $avg > 0 ? ($stdDev / $avg) : 0;
            $consistencyScore = max(0, 20 - ($cv * 40));
        }

        $score = round($deliveryScore + $errorScore + $dropScore + $consistencyScore);

        $grade = match (true) {
            $score >= 90 => 'A+',
            $score >= 80 => 'A',
            $score >= 70 => 'B',
            $score >= 60 => 'C',
            $score >= 50 => 'D',
            default      => 'F',
        };

        return [
            'score'   => $score,
            'grade'   => $grade,
            'factors' => [
                'delivery'    => round($deliveryScore, 1),
                'error_rate'  => round($errorScore, 1),
                'drop_rate'   => round($dropScore, 1),
                'consistency' => round($consistencyScore, 1),
            ],
        ];
    }

    /**
     * Estimate ad blocker impact.
     * Compares server-side event volume vs expected client-only volume.
     */
    private function estimateAdBlockerImpact(array $usage): array
    {
        $total = $usage['total_received'];
        if ($total === 0) {
            return ['estimated_blocked_percentage' => 0, 'status' => 'no_data'];
        }

        // Industry average: 27% of users have ad blockers
        $blockerRate = 0.27;
        $estimatedBlocked = (int) ($total * $blockerRate);

        return [
            'estimated_blocked_percentage' => round($blockerRate * 100, 1),
            'estimated_events_saved'       => $estimatedBlocked,
            'recommendation'               => $total > 1000
                ? 'Server-side tracking is actively recovering data from ad-blocked users.'
                : 'Increase traffic volume to see meaningful ad-blocker impact data.',
        ];
    }
}
