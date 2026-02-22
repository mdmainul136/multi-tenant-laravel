<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantDatabasePlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TenantDatabaseIsolationService
{
    /**
     * Create a dedicated MySQL user for a tenant database.
     * This user can ONLY access their own database — no cross-DB access.
     *
     * @param Tenant $tenant
     * @return array{username: string, password: string}
     */
    public function createIsolatedUser(Tenant $tenant): array
    {
        $username = 'tu_' . Str::slug($tenant->tenant_id, '_');
        // Truncate to 32 chars (MySQL username limit)
        $username = substr($username, 0, 32);
        $password = Str::random(32);

        $dbName = $tenant->database_name;

        try {
            // Drop existing user if any (idempotent)
            DB::statement("DROP USER IF EXISTS '{$username}'@'localhost'");
            DB::statement("DROP USER IF EXISTS '{$username}'@'%'");

            // Create the user
            DB::statement("CREATE USER '{$username}'@'%' IDENTIFIED BY '{$password}'");

            // Grant ONLY access to their own database
            // No GRANT OPTION, SUPER, FILE, PROCESS — pure data operations + schema changes
            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES ON `{$dbName}`.* TO '{$username}'@'%'");

            // Flush to apply
            DB::statement("FLUSH PRIVILEGES");

            // Store credentials on the tenant record
            $tenant->update([
                'db_username' => $username,
                'db_password_encrypted' => $password, // encrypted via model cast
            ]);

            Log::info("Created isolated MySQL user '{$username}' for tenant '{$tenant->tenant_id}'");

            return [
                'username' => $username,
                'password' => $password,
            ];

        } catch (\Exception $e) {
            Log::error("Failed to create isolated MySQL user for tenant '{$tenant->tenant_id}': " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Revoke all privileges for a tenant's MySQL user.
     * Called when tenant is suspended or deleted.
     *
     * @param Tenant $tenant
     */
    public function revokeAccess(Tenant $tenant): void
    {
        if (!$tenant->db_username) {
            return;
        }

        try {
            DB::statement("REVOKE ALL PRIVILEGES, GRANT OPTION FROM '{$tenant->db_username}'@'%'");
            DB::statement("FLUSH PRIVILEGES");

            Log::info("Revoked all DB access for tenant '{$tenant->tenant_id}'");

        } catch (\Exception $e) {
            Log::warning("Failed to revoke access for tenant '{$tenant->tenant_id}': " . $e->getMessage());
        }
    }

    /**
     * Drop the isolated MySQL user entirely.
     *
     * @param Tenant $tenant
     */
    public function dropIsolatedUser(Tenant $tenant): void
    {
        if (!$tenant->db_username) {
            return;
        }

        try {
            DB::statement("DROP USER IF EXISTS '{$tenant->db_username}'@'%'");
            DB::statement("FLUSH PRIVILEGES");

            $tenant->update([
                'db_username' => null,
                'db_password_encrypted' => null,
            ]);

            Log::info("Dropped isolated MySQL user for tenant '{$tenant->tenant_id}'");

        } catch (\Exception $e) {
            Log::warning("Failed to drop MySQL user for tenant '{$tenant->tenant_id}': " . $e->getMessage());
        }
    }

    /**
     * Assign a storage plan to a tenant.
     *
     * @param Tenant $tenant
     * @param string $planSlug
     * @return TenantDatabasePlan
     */
    public function setStoragePlan(Tenant $tenant, string $planSlug): TenantDatabasePlan
    {
        $plan = TenantDatabasePlan::where('slug', $planSlug)
            ->where('is_active', true)
            ->firstOrFail();

        $tenant->update(['database_plan_id' => $plan->id]);

        Log::info("Assigned '{$plan->name}' plan to tenant '{$tenant->tenant_id}'");

        return $plan;
    }

    /**
     * Check if a tenant is over their storage quota.
     *
     * @param Tenant $tenant
     * @return array{over_quota: bool, usage_mb: float, limit_mb: int, usage_percent: float}
     */
    public function checkQuotaUsage(Tenant $tenant): array
    {
        $plan = $tenant->databasePlan;

        if (!$plan) {
            // No plan assigned — no quota enforcement
            return [
                'over_quota' => false,
                'usage_mb' => 0,
                'limit_mb' => 0,
                'usage_percent' => 0,
                'plan' => null,
            ];
        }

        $latestStat = $tenant->latestDatabaseStat;
        $usageMb = $latestStat ? (float) $latestStat->database_size_mb : 0;
        $limitMb = $plan->storage_limit_mb;
        $usagePercent = $limitMb > 0 ? round(($usageMb / $limitMb) * 100, 2) : 0;

        return [
            'over_quota' => $usageMb >= $limitMb,
            'usage_mb' => $usageMb,
            'limit_mb' => $limitMb,
            'usage_percent' => $usagePercent,
            'plan' => $plan->name,
        ];
    }

    /**
     * Check if tenant is over quota (simple boolean).
     *
     * @param Tenant $tenant
     * @return bool
     */
    public function isOverQuota(Tenant $tenant): bool
    {
        return $this->checkQuotaUsage($tenant)['over_quota'];
    }
}
