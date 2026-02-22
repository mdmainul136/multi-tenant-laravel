<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantService
{
    protected DatabaseManager $databaseManager;

    public function __construct(DatabaseManager $databaseManager)
    {
        $this->databaseManager = $databaseManager;
    }

    /**
     * Create a new tenant with database provisioning.
     *
     * @param array $data
     * @return Tenant
     * @throws \Exception
     */
    public function createTenant(array $data): Tenant
    {
        // ... (validation and existence checks are already done in controller or above)

        try {
            // Generate database name
            $databaseName = Tenant::generateDatabaseName($data['tenantId']);

            // Determine domain
            $baseDomain = parse_url(config('app.url'), PHP_URL_HOST);
            $domain = $data['tenantId'] . '.' . $baseDomain;

            // Create tenant record in 'pending' state
            $tenant = Tenant::create([
                'tenant_id' => $data['tenantId'],
                'tenant_name' => $data['tenantName'],
                'name' => $data['tenantName'],
                'company_name' => $data['companyName'],
                'business_type' => $data['businessType'],
                'admin_name' => $data['adminName'],
                'database_name' => $databaseName,
                'admin_email' => $data['adminEmail'],
                'email' => $data['adminEmail'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'city' => $data['city'],
                'country' => $data['country'],
                'domain' => $domain,
                'status' => 'inactive', 
                'provisioning_status' => 'queued',
            ]);

            // Dispatch background job for long-running DB tasks
            \App\Jobs\ProvisionTenantJob::dispatch($tenant, $data['adminPassword']);

            return $tenant;
        } catch (\Exception $e) {
            \Log::error("Tenant model creation failed for {$data['tenantId']}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check the status of tenant provisioning.
     */
    public function checkProvisioningStatus(string $tenantId): array
    {
        $tenant = Tenant::where('tenant_id', $tenantId)->first();

        if (!$tenant) {
            return ['success' => false, 'message' => 'Tenant not found'];
        }

        return [
            'success' => true,
            'status' => $tenant->provisioning_status,
            'is_ready' => $tenant->status === 'active' && $tenant->provisioning_status === 'completed',
            'domain' => $tenant->domain
        ];
    }

    /**
     * Validate tenant ID format (alphanumeric and hyphens only).
     *
     * @param string $tenantId
     * @return bool
     */
    public function validateTenantId(string $tenantId): bool
    {
        return preg_match('/^[a-z0-9-]+$/', $tenantId) === 1;
    }

    /**
     * Check if tenant exists.
     *
     * @param string $tenantId
     * @return bool
     */
    public function tenantExists(string $tenantId): bool
    {
        return Tenant::where('tenant_id', $tenantId)->exists();
    }

    /**
     * Get tenant by tenant ID.
     *
     * @param string $tenantId
     * @return Tenant|null
     */
    public function getTenantByTenantId(string $tenantId): ?Tenant
    {
        return Tenant::where('tenant_id', $tenantId)->first();
    }

    /**
     * Clean up failed provisioning attempts.
     * Deletes DBs and records for tenants that stuck in 'failed' or 'pending' for too long.
     */
    public function cleanupFailedProvisioning(int $olderThanHours = 24): int
    {
        $failedTenants = Tenant::whereIn('provisioning_status', ['failed', 'pending'])
            ->where('created_at', '<=', now()->subHours($olderThanHours))
            ->get();

        $count = 0;
        foreach ($failedTenants as $tenant) {
            try {
                // Try to drop the database if it was created
                if ($tenant->provisioning_status !== 'pending') {
                    $this->databaseManager->deleteTenantDatabase($tenant->database_name);
                }
                
                Log::info("Cleaning up failed tenant: {$tenant->tenant_id}");
                $tenant->delete();
                $count++;
            } catch (\Exception $e) {
                Log::error("Failed to cleanup tenant {$tenant->tenant_id}: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Get all active tenants.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllTenants()
    {
        return Tenant::orderBy('created_at', 'desc')->get();
    }
}
