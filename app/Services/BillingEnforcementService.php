<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

class BillingEnforcementService
{
    /**
     * Check the billing health of a tenant and update status if necessary.
     *
     * @param Tenant $tenant
     * @return string Current status
     */
    public function checkTenantHealth(Tenant $tenant): string
    {
        // 1. Get overdue invoices
        $overdueCount = Invoice::where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->where('due_date', '<', now()->subDays(3)) // 3-day grace period
            ->count();

        if ($overdueCount > 0) {
            if ($tenant->status !== 'billing_failed' && $tenant->status !== 'suspended') {
                $tenant->update(['status' => 'billing_failed']);
                Log::warning("Tenant {$tenant->tenant_id} marked as 'billing_failed' due to {$overdueCount} overdue invoices.");
            }
            return $tenant->status;
        }

        // 2. Clear billing_failed if all overdue invoices are paid
        if ($tenant->status === 'billing_failed') {
            $stillOverdue = Invoice::where('tenant_id', $tenant->id)
                ->where('status', 'pending')
                ->where('due_date', '<', now())
                ->exists();
                
            if (!$stillOverdue) {
                $tenant->update(['status' => 'active']);
                Log::info("Tenant {$tenant->tenant_id} restored to 'active' status.");
            }
        }

        return $tenant->status;
    }

    /**
     * Enforce billing check for all active or billing_failed tenants.
     * Designed to be run by a daily scheduler.
     */
    public function enforceForAll(): void
    {
        Log::info("Starting daily billing enforcement check.");

        Tenant::whereIn('status', ['active', 'billing_failed'])->chunk(100, function ($tenants) {
            foreach ($tenants as $tenant) {
                $this->checkTenantHealth($tenant);
            }
        });

        Log::info("Billing enforcement check completed.");
    }
}
