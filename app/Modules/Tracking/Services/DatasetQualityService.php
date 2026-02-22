<?php

namespace App\Modules\Tracking\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Meta Dataset Quality API Service.
 *
 * Fetches real-time metrics from Meta's Graph API to monitor CAPI integration health:
 *   - Event Match Quality (EMQ) score per event type (0-10)
 *   - Match key coverage (which user_data fields are being sent and matched)
 *   - Additional Conversions Reported (ACR)
 *   - Event Coverage (server vs browser event ratio)
 *   - Deduplication rate
 *   - Data Freshness
 *
 * Endpoint: GET /{pixel_id}/dataset_quality
 * Requires: System User Token with ads_management or ads_read permission
 */
class DatasetQualityService
{
    private const API_VERSION = 'v21.0';
    private const GRAPH_API = 'https://graph.facebook.com';
    private const CACHE_TTL = 900; // 15 minutes

    /**
     * Fetch full dataset quality report from Meta.
     */
    public function getQualityReport(string $pixelId, string $accessToken): array
    {
        $cacheKey = "dataset_quality_{$pixelId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($pixelId, $accessToken) {
            try {
                $response = Http::withToken($accessToken)
                    ->timeout(30)
                    ->get(self::GRAPH_API . '/' . self::API_VERSION . "/{$pixelId}/dataset_quality", [
                        'fields' => implode(',', [
                            'event_name',
                            'event_match_quality',
                            'match_key_coverage',
                            'event_count',
                            'server_event_count',
                            'browser_event_count',
                            'deduplicated_event_count',
                        ]),
                    ]);

                if (!$response->successful()) {
                    Log::error('[Dataset Quality] API error', [
                        'status' => $response->status(),
                        'body'   => $response->json(),
                        'pixel'  => $pixelId,
                    ]);
                    return $this->fallbackReport($pixelId);
                }

                $data = $response->json('data', []);
                return $this->formatQualityReport($data, $pixelId);

            } catch (\Exception $e) {
                Log::error('[Dataset Quality] Request failed', ['error' => $e->getMessage()]);
                return $this->fallbackReport($pixelId);
            }
        });
    }

    /**
     * Fetch EMQ score for a specific event type.
     */
    public function getEventEMQ(string $pixelId, string $accessToken, string $eventName): array
    {
        $report = $this->getQualityReport($pixelId, $accessToken);
        $events = $report['events'] ?? [];

        foreach ($events as $event) {
            if (($event['event_name'] ?? '') === $eventName) {
                return $event;
            }
        }

        return [
            'event_name'          => $eventName,
            'emq_score'           => 0,
            'status'              => 'not_found',
            'recommendation'      => "No data found for '{$eventName}'. Ensure this event is being sent.",
        ];
    }

    /**
     * Fetch match key coverage breakdown.
     * Shows which user_data fields are being sent and their coverage percentage.
     */
    public function getMatchKeyCoverage(string $pixelId, string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->get(self::GRAPH_API . '/' . self::API_VERSION . "/{$pixelId}/dataset_quality", [
                    'fields' => 'event_name,match_key_coverage',
                ]);

            if (!$response->successful()) {
                return ['error' => 'Failed to fetch match key coverage'];
            }

            $data = $response->json('data', []);
            $aggregated = $this->aggregateMatchKeys($data);

            return [
                'success'      => true,
                'match_keys'   => $aggregated,
                'per_event'    => collect($data)->map(fn ($item) => [
                    'event_name' => $item['event_name'] ?? 'unknown',
                    'coverage'   => $item['match_key_coverage'] ?? [],
                ])->toArray(),
            ];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Calculate Additional Conversions Reported (ACR).
     * Estimates how many extra conversions CAPI captured vs Pixel-only.
     */
    public function getAdditionalConversions(string $pixelId, string $accessToken): array
    {
        $report = $this->getQualityReport($pixelId, $accessToken);
        $events = $report['events'] ?? [];

        $totalServer = 0;
        $totalBrowser = 0;
        $totalDeduped = 0;

        foreach ($events as $event) {
            $totalServer  += $event['server_events'] ?? 0;
            $totalBrowser += $event['browser_events'] ?? 0;
            $totalDeduped += $event['deduplicated_events'] ?? 0;
        }

        // ACR = server events that weren't matched by browser events
        $additional = max(0, $totalServer - $totalDeduped);
        $acrRate = $totalBrowser > 0 ? round(($additional / $totalBrowser) * 100, 1) : 0;

        return [
            'additional_conversions' => $additional,
            'acr_rate'               => $acrRate,
            'server_events'          => $totalServer,
            'browser_events'         => $totalBrowser,
            'deduplicated'           => $totalDeduped,
            'status'                 => $acrRate > 10 ? 'high_impact' : ($acrRate > 5 ? 'moderate' : 'low'),
            'recommendation'         => $acrRate > 10
                ? "CAPI is capturing {$acrRate}% additional conversions. Excellent ROI from server-side setup."
                : "Consider improving match key coverage to increase additional conversions.",
        ];
    }

    /**
     * Format raw API response into structured report.
     */
    private function formatQualityReport(array $data, string $pixelId): array
    {
        $events = [];
        $overallEmq = 0;
        $eventCount = 0;

        foreach ($data as $item) {
            $emq = $item['event_match_quality'] ?? 0;
            $eventName = $item['event_name'] ?? 'unknown';

            $quality = match (true) {
                $emq >= 8 => 'excellent',
                $emq >= 6 => 'good',
                $emq >= 4 => 'fair',
                default   => 'poor',
            };

            $events[] = [
                'event_name'         => $eventName,
                'emq_score'          => $emq,
                'quality'            => $quality,
                'total_events'       => $item['event_count'] ?? 0,
                'server_events'      => $item['server_event_count'] ?? 0,
                'browser_events'     => $item['browser_event_count'] ?? 0,
                'deduplicated_events' => $item['deduplicated_event_count'] ?? 0,
                'match_key_coverage' => $item['match_key_coverage'] ?? [],
            ];

            $overallEmq += $emq;
            $eventCount++;
        }

        $avgEmq = $eventCount > 0 ? round($overallEmq / $eventCount, 1) : 0;

        return [
            'pixel_id'           => $pixelId,
            'overall_emq'        => $avgEmq,
            'overall_quality'    => match (true) {
                $avgEmq >= 8 => 'excellent',
                $avgEmq >= 6 => 'good',
                $avgEmq >= 4 => 'fair',
                default      => 'poor',
            },
            'events_tracked'     => $eventCount,
            'events'             => $events,
            'fetched_at'         => now()->toIso8601String(),
        ];
    }

    /**
     * Aggregate match key coverage across all events.
     */
    private function aggregateMatchKeys(array $data): array
    {
        $keys = [];
        $count = 0;

        foreach ($data as $item) {
            $coverage = $item['match_key_coverage'] ?? [];
            foreach ($coverage as $key => $value) {
                $keys[$key] = ($keys[$key] ?? 0) + $value;
            }
            $count++;
        }

        if ($count > 0) {
            $keys = array_map(fn ($v) => round($v / $count, 1), $keys);
        }

        // Sort by coverage descending
        arsort($keys);

        return $keys;
    }

    /**
     * Fallback report when API is unavailable (uses local EMQ calculation).
     */
    private function fallbackReport(string $pixelId): array
    {
        return [
            'pixel_id'        => $pixelId,
            'overall_emq'     => 0,
            'overall_quality' => 'unknown',
            'events_tracked'  => 0,
            'events'          => [],
            'source'          => 'fallback',
            'message'         => 'Could not reach Meta API. Check your access token and pixel ID.',
            'fetched_at'      => now()->toIso8601String(),
        ];
    }
}
