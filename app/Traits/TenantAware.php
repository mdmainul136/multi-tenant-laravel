<?php

namespace App\Traits;

use App\Services\DatabaseManager;
use Illuminate\Support\Facades\Log;

trait TenantAware
{
    /**
     * The tenant ID this job belongs to.
     *
     * @var string
     */
    public string $tenantId;

    /**
     * Set the tenant ID for this job.
     *
     * @param string $tenantId
     * @return $this
     */
    public function forTenant(string $tenantId): self
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    /**
     * Ensure the tenant database is connected before handling the job.
     * This can be called in the constructor or automatically by the global Queue Guard.
     */
    public function applyTenantContext(): void
    {
        if (empty($this->tenantId)) {
            Log::warning("TenantAware Job " . get_class($this) . " dispatched without tenantId.");
            return;
        }

        try {
            $databaseManager = app(DatabaseManager::class);
            $databaseManager->switchToTenantDatabase($this->tenantId);
            
            Log::debug("Applied tenant context ({$this->tenantId}) for job: " . get_class($this));
        } catch (\Exception $e) {
            Log::error("Failed to apply tenant context in job: " . $e->getMessage());
            throw $e;
        }
    }
}
