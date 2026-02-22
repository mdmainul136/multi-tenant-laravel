<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ModuleService
{
    protected $moduleMigrationManager;

    public function __construct(ModuleMigrationManager $moduleMigrationManager)
    {
        $this->moduleMigrationManager = $moduleMigrationManager;
    }

    /**
     * Get all available modules
     */
    public function getAvailableModules()
    {
        return Module::active()->get();
    }

    /**
     * Get modules subscribed by a tenant
     */
    public function getTenantModules($tenantId)
    {
        // Convert tenant_id string to database ID
        $tenant = Tenant::where('tenant_id', $tenantId)->first();
        
        if (!$tenant) {
            return collect([]);
        }

        return TenantModule::with('module')
            ->where('tenant_id', $tenant->id)
            ->active()
            ->get()
            ->map(function ($tm) {
                return [
                    'id' => $tm->id,
                    'module_key' => $tm->module->module_key,
                    'module_name' => $tm->module->module_name,
                    'description' => $tm->module->description,
                    'price' => $tm->module->price,
                    'status' => $tm->status,
                    'subscribed_at' => $tm->subscribed_at,
                    'expires_at' => $tm->expires_at,
                ];
            });
    }

    /**
     * Check if tenant has access to a module
     */
    public function isModuleActive($tenantId, string $moduleKey): bool
    {
        // Convert tenant_id string to database ID
        $tenant = Tenant::where('tenant_id', $tenantId)->first();
        
        if (!$tenant) {
            return false;
        }

        $module = Module::where('module_key', $moduleKey)->first();
        
        if (!$module) {
            return false;
        }

        $subscription = TenantModule::where('tenant_id', $tenant->id)
            ->where('module_id', $module->id)
            ->first();

        return $subscription && $subscription->isActive();
    }

    /**
     * Subscribe tenant to a module
     */
    public function subscribeModule($tenantId, string $moduleKey, array $options = [])
    {
        try {
            DB::connection('mysql')->beginTransaction();

            // Get tenant and module (handle both string tenant_id and numeric id)
            $tenant = is_numeric($tenantId) 
                ? Tenant::findOrFail($tenantId)
                : Tenant::where('tenant_id', $tenantId)->firstOrFail();
            $module = Module::where('module_key', $moduleKey)
                ->where('is_active', true)
                ->firstOrFail();

            // Check if already subscribed
            $existing = TenantModule::where('tenant_id', $tenant->id)
                ->where('module_id', $module->id)
                ->first();

            if ($existing && $existing->isActive()) {
                throw new \Exception('Already subscribed to this module');
            }

            // Create or update subscription
            $subscription = TenantModule::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'module_id' => $module->id,
                ],
                [
                    'status' => $options['status'] ?? 'active',
                    'subscription_type' => $options['subscription_type'] ?? 'monthly',
                    'price_paid' => $options['price_paid'] ?? null,
                    'subscribed_at' => now(),
                    'starts_at' => $options['starts_at'] ?? now(),
                    'expires_at' => $options['expires_at'] ?? null,
                    'auto_renew' => $options['auto_renew'] ?? true,
                    'payment_id' => $options['payment_id'] ?? null,
                ]
            );

            // Run module migrations on tenant database
            $this->moduleMigrationManager->runModuleMigrations(
                $tenant->database_name,
                $moduleKey
            );

            DB::connection('mysql')->commit();

            Log::info("Tenant {$tenantId} subscribed to module {$moduleKey}");

            return [
                'success' => true,
                'message' => "Successfully subscribed to {$module->module_name}",
                'subscription' => $subscription,
            ];

        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();
            
            Log::error("Failed to subscribe tenant {$tenantId} to module {$moduleKey}: " . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Unsubscribe tenant from a module
     */
    public function unsubscribeModule($tenantId, string $moduleKey)
    {
        try {
            DB::connection('mysql')->beginTransaction();

            // Handle both string tenant_id and numeric id
            $tenant = is_numeric($tenantId) 
                ? Tenant::findOrFail($tenantId)
                : Tenant::where('tenant_id', $tenantId)->firstOrFail();
            $module = Module::where('module_key', $moduleKey)->firstOrFail();

            $subscription = TenantModule::where('tenant_id', $tenant->id)
                ->where('module_id', $module->id)
                ->firstOrFail();

            // Update status to inactive instead of deleting
            $subscription->update(['status' => 'inactive']);

            // Perform non-destructive rollback (archives tables)
            $this->moduleMigrationManager->rollbackModuleMigrations(
                $tenant->database_name,
                $moduleKey
            );

            DB::connection('mysql')->commit();

            Log::info("Tenant {$tenantId} unsubscribed from module {$moduleKey}");

            return [
                'success' => true,
                'message' => "Successfully unsubscribed from {$module->module_name}",
            ];

        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();
            
            Log::error("Failed to unsubscribe tenant {$tenantId} from module {$moduleKey}: " . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Determine which modules to auto-activate during tenant onboarding.
     * Merges business-type core modules with region core modules (union, no duplicates).
     */
    public function getModulesForOnboarding(Tenant $tenant): array
    {
        $businessType = $tenant->business_type ?? 'sole_proprietorship';
        $country = $tenant->country ?? '';

        // 1. Business-type core modules
        $btConfig = config("business_modules.{$businessType}", []);
        $btCore = $btConfig['core'] ?? ['ecommerce', 'crm'];

        // 2. Region core modules
        $regionCore = [];
        $regions = config('tenant_regions', []);
        foreach ($regions as $regionKey => $region) {
            $countries = $region['countries'] ?? [];
            if ($countries === '*' || (is_array($countries) && in_array(strtoupper($country), $countries))) {
                $regionCore = $region['modules']['core'] ?? [];
                break;
            }
        }

        // Merge (union) and deduplicate
        return array_values(array_unique(array_merge($btCore, $regionCore)));
    }

    /**
     * Get recommended (but not auto-activated) modules for a tenant.
     * Combines business-type recommendations, region addons, and related-module suggestions.
     */
    public function getRecommendedModules(string $tenantId): array
    {
        $tenant = is_numeric($tenantId)
            ? Tenant::find($tenantId)
            : Tenant::where('tenant_id', $tenantId)->first();

        if (!$tenant) {
            return [];
        }

        // Already-active module keys
        $activeKeys = TenantModule::where('tenant_id', $tenant->id)
            ->whereIn('status', ['active', 'trial'])
            ->get()
            ->map(fn ($tm) => optional($tm->module)->module_key)
            ->filter()
            ->toArray();

        $suggestions = [];

        // 1. Business-type recommended
        $btConfig = config("business_modules.{$tenant->business_type}", []);
        $suggestions = array_merge($suggestions, $btConfig['recommended'] ?? []);

        // 2. Region addons
        $regions = config('tenant_regions', []);
        foreach ($regions as $region) {
            $countries = $region['countries'] ?? [];
            if ($countries === '*' || (is_array($countries) && in_array(strtoupper($tenant->country ?? ''), $countries))) {
                $suggestions = array_merge($suggestions, array_keys($region['modules']['addons'] ?? []));
                break;
            }
        }

        // 3. Related modules for currently active modules
        $relationships = config('business_modules.relationships', []);
        foreach ($activeKeys as $key) {
            $suggestions = array_merge($suggestions, $relationships[$key] ?? []);
        }

        // Remove already-active and deduplicate
        $suggestions = array_values(array_unique(array_diff($suggestions, $activeKeys)));

        // Enrich with Module model data
        $modules = Module::whereIn('module_key', $suggestions)->active()->get();
        $conf = config('modules', []);

        return $modules->map(function (Module $m) use ($conf) {
            $c = $conf[$m->module_key] ?? [];
            return [
                'id'          => $m->id,
                'module_key'  => $m->module_key,
                'module_name' => $m->module_name,
                'description' => $m->description,
                'price'       => (float) $m->price,
                'icon'        => $c['icon'] ?? 'box',
                'color'       => $c['color'] ?? '#6366f1',
            ];
        })->values()->toArray();
    }

    /**
     * Get related / complementary modules for a given module key.
     * Excludes modules the tenant already has active.
     */
    public function getRelatedModules(string $tenantId, string $moduleKey): array
    {
        $tenant = is_numeric($tenantId)
            ? Tenant::find($tenantId)
            : Tenant::where('tenant_id', $tenantId)->first();

        $relationships = config('business_modules.relationships', []);
        $relatedKeys = $relationships[$moduleKey] ?? [];

        // Exclude already-active
        if ($tenant) {
            $activeKeys = TenantModule::where('tenant_id', $tenant->id)
                ->whereIn('status', ['active', 'trial'])
                ->get()
                ->map(fn ($tm) => optional($tm->module)->module_key)
                ->filter()
                ->toArray();

            $relatedKeys = array_diff($relatedKeys, $activeKeys);
        }

        $modules = Module::whereIn('module_key', $relatedKeys)->active()->get();
        $conf = config('modules', []);

        return $modules->map(function (Module $m) use ($conf) {
            $c = $conf[$m->module_key] ?? [];
            return [
                'id'          => $m->id,
                'module_key'  => $m->module_key,
                'module_name' => $m->module_name,
                'description' => $m->description,
                'price'       => (float) $m->price,
                'icon'        => $c['icon'] ?? 'box',
                'color'       => $c['color'] ?? '#6366f1',
            ];
        })->values()->toArray();
    }

    /**
     * Get module statistics
     */
    public function getModuleStats(string $moduleKey)
    {
        $module = Module::where('module_key', $moduleKey)->first();
        
        if (!$module) {
            return null;
        }

        return [
            'total_subscriptions' => $module->tenantModules()->count(),
            'active_subscriptions' => $module->activeSubscriptions()->count(),
            'revenue' => $module->activeSubscriptions()->count() * $module->price,
        ];
    }
}
