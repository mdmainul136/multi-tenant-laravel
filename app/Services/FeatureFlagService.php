<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class FeatureFlagService
{
    /**
     * Check if a feature is enabled for a specific tenant.
     * Checks both the global module access and the granular feature_flags JSON.
     *
     * @param string $feature
     * @param string|null $tenantId
     * @return bool
     */
    public function isEnabled(string $feature, ?string $tenantId = null): bool
    {
        $tenantId = $tenantId ?: request()->attributes->get('tenant_id');

        if (!$tenantId) {
            return false;
        }

        $tenant = Tenant::where('tenant_id', $tenantId)->first();

        if (!$tenant) {
            return false;
        }

        // 1. Check Granular Feature Flags (Overwrites everything else)
        $flags = $tenant->feature_flags ?: [];
        if (isset($flags[$feature])) {
            return (bool) $flags[$feature];
        }

        // 2. Check Module Access (Mapping features to modules)
        // This is a simplified check. In a real system, you might have a map:
        // 'advanced_analytics' => 'analytics'
        // 'zatca_einvoicing' => 'zatca'
        
        $moduleMap = config('tenant_features.module_map', []);
        $requiredModule = $moduleMap[$feature] ?? null;

        if ($requiredModule) {
            return $tenant->tenantModules()->where('module_key', $requiredModule)->where('status', 'active')->exists();
        }

        return false;
    }

    /**
     * Set a feature flag for a tenant.
     */
    public function setFlag(string $tenantId, string $feature, bool $enabled): void
    {
        $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();
        
        $flags = $tenant->feature_flags ?: [];
        $flags[$feature] = $enabled;
        
        $tenant->feature_flags = $flags;
        $tenant->save();

        Log::info("Feature flag '$feature' set to " . ($enabled ? 'ON' : 'OFF') . " for tenant: $tenantId");
    }
}
