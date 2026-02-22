<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IorScraperHealthService
{
    /**
     * Record a health signal for a marketplace.
     */
    public function recordSignal(string $marketplace, string $status, int $durationMs, ?string $error = null): void
    {
        try {
            DB::table('ior_scraper_health_stats')->updateOrInsert(
                ['marketplace' => $marketplace],
                [
                    'success_count'   => DB::raw($status === 'success' ? 'success_count + 1' : 'success_count'),
                    'failure_count'   => DB::raw($status === 'failed'  ? 'failure_count + 1' : 'failure_count'),
                    'blocked_count'   => DB::raw($error === 'blocked'  ? 'blocked_count + 1' : 'blocked_count'),
                    'avg_duration_ms' => $this->calculateNewAvg($marketplace, $durationMs),
                    'last_error'      => $error,
                    'last_success_at' => $status === 'success' ? now() : DB::raw('last_success_at'),
                    'updated_at'      => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::error("[IOR Health] Failed to record signal: " . $e->getMessage());
        }
    }

    private function calculateNewAvg(string $marketplace, int $newDuration): int
    {
        $current = DB::table('ior_scraper_health_stats')->where('marketplace', $marketplace)->first();
        if (!$current) return $newDuration;

        $totalCount = $current->success_count + $current->failure_count;
        if ($totalCount === 0) return $newDuration;

        return (int) (($current->avg_duration_ms * $totalCount + $newDuration) / ($totalCount + 1));
    }
}
