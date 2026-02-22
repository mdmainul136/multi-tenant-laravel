<?php

namespace App\Modules\Tracking\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Cross-Channel Attribution Service.
 *
 * Multi-touch attribution modeling across all 9 ad channels:
 *   - Record touchpoints (clicks, views, conversions)
 *   - First-touch attribution
 *   - Last-touch attribution
 *   - Linear attribution (equal credit)
 *   - Time-decay attribution (recent touches get more credit)
 *   - Position-based attribution (40% first + 40% last + 20% middle)
 *   - Attribution window (configurable, default 30 days)
 *   - Conversion path analysis
 */
class AttributionService
{
    private const TABLE = 'ec_tracking_attribution';
    private const DEFAULT_WINDOW_DAYS = 30;

    /**
     * Record a touchpoint.
     */
    public function recordTouch(int $containerId, array $event): int
    {
        $visitorId = $event['visitor_id'] ?? $event['user_data']['external_id'] ?? 'unknown';

        return DB::table(self::TABLE)->insertGetId([
            'container_id'     => $containerId,
            'visitor_id'       => $visitorId,
            'session_id'       => $event['session_id'] ?? null,
            'channel'          => $this->detectChannel($event),
            'event_name'       => $event['event_name'] ?? 'unknown',
            'campaign'         => $event['utm']['utm_campaign'] ?? $event['campaign'] ?? null,
            'source'           => $event['utm']['utm_source'] ?? $event['source'] ?? null,
            'medium'           => $event['utm']['utm_medium'] ?? $event['medium'] ?? null,
            'click_id'         => $this->extractClickId($event),
            'click_id_type'    => $this->extractClickIdType($event),
            'is_conversion'    => $this->isConversion($event['event_name'] ?? ''),
            'conversion_value' => $event['custom_data']['value'] ?? null,
            'currency'         => $event['custom_data']['currency'] ?? null,
            'touched_at'       => Carbon::createFromTimestamp($event['event_time'] ?? time()),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /**
     * Get attributed conversions for a container using specified model.
     */
    public function getAttribution(int $containerId, string $model = 'last_touch', int $days = null): array
    {
        $days = $days ?? self::DEFAULT_WINDOW_DAYS;
        $since = Carbon::now()->subDays($days);

        // Get all conversions in window
        $conversions = DB::table(self::TABLE)
            ->where('container_id', $containerId)
            ->where('is_conversion', true)
            ->where('touched_at', '>=', $since)
            ->get();

        $results = [];

        foreach ($conversions as $conversion) {
            // Get all touchpoints for this visitor before conversion
            $touchpoints = DB::table(self::TABLE)
                ->where('container_id', $containerId)
                ->where('visitor_id', $conversion->visitor_id)
                ->where('touched_at', '<=', $conversion->touched_at)
                ->where('touched_at', '>=', Carbon::parse($conversion->touched_at)->subDays($days))
                ->orderBy('touched_at')
                ->get()
                ->toArray();

            if (empty($touchpoints)) continue;

            $value = (float) ($conversion->conversion_value ?? 0);
            $credits = $this->calculateCredits($touchpoints, $model, $value);

            foreach ($credits as $credit) {
                $key = $credit['channel'] . '_' . ($credit['campaign'] ?? 'direct');
                if (!isset($results[$key])) {
                    $results[$key] = [
                        'channel'      => $credit['channel'],
                        'campaign'     => $credit['campaign'],
                        'source'       => $credit['source'],
                        'medium'       => $credit['medium'],
                        'conversions'  => 0,
                        'value'        => 0,
                        'touchpoints'  => 0,
                    ];
                }
                $results[$key]['conversions'] += $credit['credit'];
                $results[$key]['value'] += $credit['value'];
                $results[$key]['touchpoints']++;
            }
        }

        // Sort by attributed value
        usort($results, fn ($a, $b) => $b['value'] <=> $a['value']);

        return [
            'model'           => $model,
            'window_days'     => $days,
            'total_conversions' => $conversions->count(),
            'total_value'     => $conversions->sum('conversion_value'),
            'attributed'      => array_values($results),
        ];
    }

    /**
     * Get conversion paths (journey analysis).
     */
    public function getConversionPaths(int $containerId, int $days = null, int $limit = 50): array
    {
        $days = $days ?? self::DEFAULT_WINDOW_DAYS;
        $since = Carbon::now()->subDays($days);

        $conversions = DB::table(self::TABLE)
            ->where('container_id', $containerId)
            ->where('is_conversion', true)
            ->where('touched_at', '>=', $since)
            ->select('visitor_id', 'touched_at', 'conversion_value')
            ->limit($limit)
            ->get();

        $paths = [];
        foreach ($conversions as $conv) {
            $touches = DB::table(self::TABLE)
                ->where('container_id', $containerId)
                ->where('visitor_id', $conv->visitor_id)
                ->where('touched_at', '<=', $conv->touched_at)
                ->where('touched_at', '>=', Carbon::parse($conv->touched_at)->subDays($days))
                ->orderBy('touched_at')
                ->select('channel', 'event_name', 'source', 'medium', 'campaign', 'touched_at')
                ->get()
                ->toArray();

            $path = array_map(fn ($t) => $t->channel . ($t->campaign ? "({$t->campaign})" : ''), $touches);
            $pathKey = implode(' → ', $path);

            if (!isset($paths[$pathKey])) {
                $paths[$pathKey] = [
                    'path'        => $path,
                    'path_string' => $pathKey,
                    'count'       => 0,
                    'total_value' => 0,
                    'avg_length'  => count($touches),
                ];
            }
            $paths[$pathKey]['count']++;
            $paths[$pathKey]['total_value'] += (float) $conv->conversion_value;
        }

        usort($paths, fn ($a, $b) => $b['count'] <=> $a['count']);
        return array_values(array_slice($paths, 0, 20));
    }

    /**
     * Calculate attribution credits based on model.
     */
    private function calculateCredits(array $touchpoints, string $model, float $totalValue): array
    {
        $count = count($touchpoints);
        $credits = [];

        foreach ($touchpoints as $i => $tp) {
            $credit = match ($model) {
                'first_touch'    => $i === 0 ? 1.0 : 0.0,
                'last_touch'     => $i === $count - 1 ? 1.0 : 0.0,
                'linear'         => 1.0 / $count,
                'position_based' => $this->positionCredit($i, $count),
                'time_decay'     => $this->timeDecayCredit($i, $count),
                default          => $i === $count - 1 ? 1.0 : 0.0,
            };

            if ($credit > 0) {
                $credits[] = [
                    'channel'  => $tp->channel,
                    'campaign' => $tp->campaign,
                    'source'   => $tp->source,
                    'medium'   => $tp->medium,
                    'credit'   => round($credit, 4),
                    'value'    => round($totalValue * $credit, 2),
                ];
            }
        }

        return $credits;
    }

    private function positionCredit(int $index, int $total): float
    {
        if ($total === 1) return 1.0;
        if ($total === 2) return 0.5;
        if ($index === 0) return 0.4;
        if ($index === $total - 1) return 0.4;
        return 0.2 / ($total - 2);
    }

    private function timeDecayCredit(int $index, int $total): float
    {
        // Exponential decay with half-life at midpoint
        $weight = pow(2, ($index - $total + 1) / max(1, $total / 2));
        // Normalize
        $totalWeight = 0;
        for ($i = 0; $i < $total; $i++) {
            $totalWeight += pow(2, ($i - $total + 1) / max(1, $total / 2));
        }
        return $totalWeight > 0 ? $weight / $totalWeight : 1.0 / $total;
    }

    private function detectChannel(array $event): string
    {
        // Check click IDs
        $clickIds = $event['click_ids'] ?? [];
        foreach ($clickIds as $param => $info) {
            return $info['platform'] ?? 'unknown';
        }

        // Fallback to UTM or traffic source
        return $event['traffic_source'] ?? $event['utm']['utm_source'] ?? 'direct';
    }

    private function extractClickId(array $event): ?string
    {
        $clickIds = $event['click_ids'] ?? [];
        foreach ($clickIds as $param => $info) {
            return $info['value'] ?? null;
        }
        return null;
    }

    private function extractClickIdType(array $event): ?string
    {
        $clickIds = $event['click_ids'] ?? [];
        foreach ($clickIds as $param => $info) {
            return $param;
        }
        return null;
    }

    private function isConversion(string $eventName): bool
    {
        $conversions = ['purchase', 'completepayment', 'lead', 'sign_up', 'subscribe', 'completeregistration', 'checkout'];
        return in_array(strtolower($eventName), $conversions);
    }
}
