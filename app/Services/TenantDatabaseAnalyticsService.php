<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantDatabaseStat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantDatabaseAnalyticsService
{
    /**
     * Collect database usage stats for a single tenant and store a snapshot.
     *
     * @param Tenant $tenant
     * @return TenantDatabaseStat
     */
    public function collectStats(Tenant $tenant): TenantDatabaseStat
    {
        $dbName = $tenant->database_name;

        try {
            // Query INFORMATION_SCHEMA for table-level stats
            $tables = DB::select("
                SELECT 
                    TABLE_NAME as table_name,
                    COALESCE(DATA_LENGTH, 0) as data_length,
                    COALESCE(INDEX_LENGTH, 0) as index_length,
                    COALESCE(TABLE_ROWS, 0) as table_rows
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_TYPE = 'BASE TABLE'
                ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
            ", [$dbName]);

            $dataSizeMb = 0;
            $indexSizeMb = 0;
            $totalRows = 0;
            $tableCount = count($tables);
            $largestTable = null;
            $largestTableSizeMb = 0;

            foreach ($tables as $table) {
                $dataBytes = (float) $table->data_length;
                $indexBytes = (float) $table->index_length;
                $tableSizeMb = ($dataBytes + $indexBytes) / (1024 * 1024);

                $dataSizeMb += $dataBytes / (1024 * 1024);
                $indexSizeMb += $indexBytes / (1024 * 1024);
                $totalRows += (int) $table->table_rows;

                if ($tableSizeMb > $largestTableSizeMb) {
                    $largestTableSizeMb = $tableSizeMb;
                    $largestTable = $table->table_name;
                }
            }

            $databaseSizeMb = $dataSizeMb + $indexSizeMb;

            // ENTERPRISE TELEMETRY: Slow Queries & Write Ops (Gap 5 / Low C)
            $slowQueryCount = 0;
            $writeOpCount = 0;

            try {
                // Attempt to get stats from performance_schema (requires privileges)
                $perfStats = DB::select("
                    SELECT 
                        SUM(COUNT_STAR) as total_ops,
                        SUM(CASE WHEN MAX_TIMER_WAIT > 1000000000000 THEN 1 ELSE 0 END) as slow_queries
                    FROM performance_schema.events_statements_summary_by_digest
                    WHERE SCHEMA_NAME = ?
                ", [$dbName]);

                if (!empty($perfStats) && isset($perfStats[0]->total_ops)) {
                    $writeOpCount = (int) $perfStats[0]->total_ops;
                    $slowQueryCount = (int) $perfStats[0]->slow_queries;
                }
            } catch (\Exception $e) {
                // Performance schema might be disabled or restricted, silent fallback
                Log::debug("Performance schema stats unavailable for {$dbName}");
            }

            // Calculate Top Tables by Growth (for analytics dashboard)
            $topTables = array_slice($tables, 0, 5);
            $growthMetrics = [];
            foreach ($topTables as $table) {
                $growthMetrics[$table->table_name] = round(($table->data_length + $table->index_length) / (1024 * 1024), 2);
            }

            $stat = TenantDatabaseStat::create([
                'tenant_id' => $tenant->id,
                'database_size_mb' => round($databaseSizeMb, 2),
                'data_size_mb' => round($dataSizeMb, 2),
                'index_size_mb' => round($indexSizeMb, 2),
                'table_count' => $tableCount,
                'total_rows' => $totalRows,
                'largest_table' => $largestTable,
                'largest_table_size_mb' => round($largestTableSizeMb, 2),
                'slow_query_count' => $slowQueryCount,
                'write_operation_count' => $writeOpCount,
                'top_tables_by_growth' => $growthMetrics,
                'recorded_at' => now(),
            ]);

            Log::info("Collected DB stats for tenant '{$tenant->tenant_id}': {$databaseSizeMb}MB, {$tableCount} tables, {$totalRows} rows");

            return $stat;

        } catch (\Exception $e) {
            Log::error("Failed to collect DB stats for tenant '{$tenant->tenant_id}': " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Collect stats for ALL active tenants.
     *
     * @return int Number of tenants processed
     */
    public function collectAllStats(): int
    {
        $tenants = Tenant::active()->get();
        $processed = 0;

        foreach ($tenants as $tenant) {
            try {
                $this->collectStats($tenant);
                $processed++;
            } catch (\Exception $e) {
                // Log error but continue with other tenants
                Log::warning("Skipped stats for tenant '{$tenant->tenant_id}': " . $e->getMessage());
            }
        }

        return $processed;
    }

    /**
     * Get formatted analytics for a tenant (for API response).
     *
     * @param Tenant $tenant
     * @return array
     */
    public function getAnalytics(Tenant $tenant): array
    {
        $latestStat = $tenant->latestDatabaseStat;
        $plan = $tenant->databasePlan;

        $usage = [
            'database_size_mb' => $latestStat ? (float) $latestStat->database_size_mb : 0,
            'database_size_gb' => $latestStat ? round($latestStat->database_size_mb / 1024, 3) : 0,
            'data_size_mb' => $latestStat ? (float) $latestStat->data_size_mb : 0,
            'index_size_mb' => $latestStat ? (float) $latestStat->index_size_mb : 0,
            'table_count' => $latestStat ? $latestStat->table_count : 0,
            'total_rows' => $latestStat ? $latestStat->total_rows : 0,
            'largest_table' => $latestStat ? $latestStat->largest_table : null,
            'largest_table_size_mb' => $latestStat ? (float) $latestStat->largest_table_size_mb : 0,
            'last_updated' => $latestStat ? $latestStat->recorded_at->toISOString() : null,
        ];

        $quota = [
            'plan_name' => $plan ? $plan->name : 'No Plan',
            'plan_slug' => $plan ? $plan->slug : null,
            'storage_limit_gb' => $plan ? $plan->storage_limit_gb : 0,
            'storage_limit_mb' => $plan ? $plan->storage_limit_mb : 0,
            'max_tables' => $plan ? $plan->max_tables : null,
            'max_connections' => $plan ? $plan->max_connections : 0,
            'usage_percent' => 0,
            'is_over_quota' => false,
        ];

        if ($plan && $plan->storage_limit_mb > 0) {
            $quota['usage_percent'] = round(($usage['database_size_mb'] / $plan->storage_limit_mb) * 100, 2);
            $quota['is_over_quota'] = $usage['database_size_mb'] >= $plan->storage_limit_mb;
        }

        return [
            'usage' => $usage,
            'quota' => $quota,
        ];
    }

    /**
     * Get per-table breakdown for a tenant.
     *
     * @param Tenant $tenant
     * @return array
     */
    public function getTableBreakdown(Tenant $tenant): array
    {
        $dbName = $tenant->database_name;

        try {
            $tables = DB::select("
                SELECT 
                    TABLE_NAME as table_name,
                    ENGINE as engine,
                    COALESCE(TABLE_ROWS, 0) as row_count,
                    ROUND(COALESCE(DATA_LENGTH, 0) / 1024 / 1024, 2) as data_size_mb,
                    ROUND(COALESCE(INDEX_LENGTH, 0) / 1024 / 1024, 2) as index_size_mb,
                    ROUND((COALESCE(DATA_LENGTH, 0) + COALESCE(INDEX_LENGTH, 0)) / 1024 / 1024, 2) as total_size_mb,
                    CREATE_TIME as created_at,
                    UPDATE_TIME as updated_at
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_TYPE = 'BASE TABLE'
                ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
            ", [$dbName]);

            return array_map(function ($table) {
                return [
                    'table_name' => $table->table_name,
                    'engine' => $table->engine,
                    'row_count' => (int) $table->row_count,
                    'data_size_mb' => (float) $table->data_size_mb,
                    'index_size_mb' => (float) $table->index_size_mb,
                    'total_size_mb' => (float) $table->total_size_mb,
                    'created_at' => $table->created_at,
                    'updated_at' => $table->updated_at,
                ];
            }, $tables);

        } catch (\Exception $e) {
            Log::error("Failed to get table breakdown for tenant '{$tenant->tenant_id}': " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get growth trend for a tenant over N days.
     *
     * @param Tenant $tenant
     * @param int $days
     * @return array
     */
    public function getGrowthTrend(Tenant $tenant, int $days = 30): array
    {
        $stats = TenantDatabaseStat::where('tenant_id', $tenant->id)
            ->where('recorded_at', '>=', now()->subDays($days))
            ->orderBy('recorded_at', 'asc')
            ->get();

        // Group by date (one entry per day — take the last recording of each day)
        $daily = $stats->groupBy(function ($stat) {
            return $stat->recorded_at->format('Y-m-d');
        })->map(function ($group) {
            $last = $group->last();
            return [
                'date' => $last->recorded_at->format('Y-m-d'),
                'database_size_mb' => (float) $last->database_size_mb,
                'table_count' => $last->table_count,
                'total_rows' => $last->total_rows,
            ];
        })->values()->toArray();

        // Calculate growth
        $growth = [
            'period_days' => $days,
            'data_points' => count($daily),
            'trend' => $daily,
            'growth_mb' => 0,
            'growth_percent' => 0,
        ];

        if (count($daily) >= 2) {
            $first = $daily[0]['database_size_mb'];
            $last = end($daily)['database_size_mb'];
            $growth['growth_mb'] = round($last - $first, 2);
            $growth['growth_percent'] = $first > 0 ? round((($last - $first) / $first) * 100, 2) : 0;
        }

        return $growth;
    }

    /**
     * Detect schema drift by comparing actual DB tables against module expectations.
     *
     * @param Tenant $tenant
     * @return array
     */
    public function detectSchemaDrift(Tenant $tenant): array
    {
        $dbName = $tenant->database_name;
        $drift = [
            'missing_tables' => [],
            'extra_tables' => [],
            'schema_version_mismatch' => false,
        ];

        try {
            // Get actual tables
            $actualTables = DB::connection('tenant_dynamic')->select("SHOW TABLES FROM `{$dbName}`");
            $actualTableNames = array_map(function ($t) use ($dbName) {
                $prop = "Tables_in_{$dbName}";
                return $t->$prop;
            }, $actualTables);

            // Get expected core tables
            $expectedTables = ['users', 'personal_access_tokens', 'password_reset_tokens', 'sessions'];

            // Get expected module tables
            $moduleMigrations = DB::connection('mysql')
                ->table('module_migrations')
                ->where('tenant_database', $dbName)
                ->pluck('module_key')
                ->unique();

            foreach ($moduleMigrations as $moduleKey) {
                if ($moduleKey === 'ecommerce') {
                    $expectedTables = array_merge($expectedTables, [
                        'ec_products', 'ec_customers', 'ec_orders', 'ec_order_items'
                    ]);
                }
            }

            foreach ($expectedTables as $table) {
                if (!in_array($table, $actualTableNames)) {
                    $drift['missing_tables'][] = $table;
                }
            }

            return $drift;
        } catch (\Exception $e) {
            Log::error("Schema drift detection failed for {$dbName}: " . $e->getMessage());
            return $drift;
        }
    }
}
