<?php

namespace App\Console\Commands;

use App\Models\TenantSubscription;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessSubscriptionRenewals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:process-renewals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process subscription renewals, trial expirations, and status updates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting subscription renewal processing...');

        // 1. Handle Trial Expirations
        $this->processTrialExpirations();

        // 2. Handle Auto-Renewals
        $this->processAutoRenewals();

        // 3. Handle Canceled Subscriptions reaching their end date
        $this->processEndedSubscriptions();

        $this->info('Subscription processing completed.');
    }

    protected function processTrialExpirations()
    {
        $expiredTrials = TenantSubscription::where('status', 'active')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->get();

        foreach ($expiredTrials as $sub) {
            $this->info("Trial expired for tenant: {$sub->tenant->tenant_id}");
            
            // If auto-renew is on, we would ideally charge them here
            // For now, we'll mark as expired if no payment method or auto-renew is off
            if (!$sub->auto_renew) {
                $sub->update(['status' => 'expired']);
                Log::info("Subscription trial expired for tenant {$sub->tenant->tenant_id}");
            } else {
                // In a real system, initiate Stripe charge here
                // For this demo, let's assume successful renewal if auto_renew is true
                $sub->update([
                    'status' => 'active',
                    'trial_ends_at' => null,
                    'renews_at' => now()->addMonth(),
                ]);
                Log::info("Trial converted to active subscription for tenant {$sub->tenant->tenant_id}");
            }
        }
    }

    protected function processAutoRenewals()
    {
        $toRenew = TenantSubscription::where('status', 'active')
            ->whereNull('trial_ends_at')
            ->where('auto_renew', true)
            ->where('renews_at', '<=', now())
            ->get();

        foreach ($toRenew as $sub) {
            $this->info("Renewing subscription for tenant: {$sub->tenant->tenant_id}");
            
            // Logic to extend based on billing cycle
            $nextRenewal = $sub->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth();
            
            $sub->update([
                'renews_at' => $nextRenewal,
            ]);
            
            Log::info("Subscription renewed for tenant {$sub->tenant->tenant_id}");
        }
    }

    protected function processEndedSubscriptions()
    {
        $ended = TenantSubscription::where('status', 'canceled')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->get();

        foreach ($ended as $sub) {
            $this->info("Marking canceled subscription as expired for tenant: {$sub->tenant->tenant_id}");
            $sub->update(['status' => 'expired']);
            Log::info("Canceled subscription finally expired for tenant {$sub->tenant->tenant_id}");
        }
    }
}
