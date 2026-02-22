<?php

namespace App\Modules\Tracking\Services;

use App\Models\Tracking\TrackingContainer;
use Illuminate\Support\Facades\DB;

class TrackingUsageService
{
    /**
     * Increment the daily usage counter for a container.
     */
    public function recordEvent(int $containerId, string $type = 'received'): void
    {
        $column = match ($type) {
            'forwarded'     => 'events_forwarded',
            'dropped'       => 'events_dropped',
            'power_up'      => 'power_ups_invoked',
            default         => 'events_received',
        };

        DB::connection('tenant_dynamic')->table('ec_tracking_usage')
            ->updateOrInsert(
                ['container_id' => $containerId, 'date' => now()->toDateString()],
                [$column => DB::raw("{$column} + 1"), 'updated_at' => now()]
            );
    }

    /**
     * Get usage stats for billing.
     */
    public function getUsageForBilling(int $containerId, ?string $from = null, ?string $to = null): array
    {
        $query = DB::connection('tenant_dynamic')->table('ec_tracking_usage')
            ->where('container_id', $containerId);

        if ($from) $query->where('date', '>=', $from);
        if ($to) $query->where('date', '<=', $to);

        $usage = $query->selectRaw('
            SUM(events_received) as total_received,
            SUM(events_forwarded) as total_forwarded,
            SUM(events_dropped) as total_dropped,
            SUM(power_ups_invoked) as total_power_ups
        ')->first();

        return [
            'events_received'  => (int) ($usage->total_received ?? 0),
            'events_forwarded' => (int) ($usage->total_forwarded ?? 0),
            'events_dropped'   => (int) ($usage->total_dropped ?? 0),
            'power_ups_used'   => (int) ($usage->total_power_ups ?? 0),
        ];
    }

    /**
     * Get daily breakdown for charts.
     */
    public function getDailyBreakdown(int $containerId, int $days = 30): array
    {
        return DB::connection('tenant_dynamic')->table('ec_tracking_usage')
            ->where('container_id', $containerId)
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('date')
            ->get()
            ->toArray();
    }
    /**
     * Check if a container has exceeded its monthly event quota.
     */
    public function hasReachedLimit(int $containerId, string $tier = 'free'): bool
    {
        $limit = config("tracking.tiers.{$tier}.event_limit", 100000);
        
        $usage = DB::connection('tenant_dynamic')->table('ec_tracking_usage')
            ->where('container_id', $containerId)
            ->where('date', '>=', now()->startOfMonth()->toDateString())
            ->sum('events_received');

        return $usage >= $limit;
    }
}
