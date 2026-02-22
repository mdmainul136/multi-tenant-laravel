<?php

namespace App\Console\Commands;

use App\Models\TenantModule;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends expiry warning emails to tenants:
 *  - 7 days before expiry
 *  - 3 days before expiry
 *  - On renewal_failed (immediate alert)
 */
class SendExpiryNotifications extends Command
{
    protected $signature   = 'subscriptions:notify-expiry';
    protected $description = 'Send expiry warning and renewal-failed notification emails to tenants';

    public function handle(): int
    {
        $this->info('[' . now() . '] Sending expiry notifications...');

        $sent = 0;

        // ── 7-day warning ───────────────────────────────────────────────────
        $sevenDay = TenantModule::with(['tenant', 'module'])
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->addDays(6)->startOfDay(), now()->addDays(7)->endOfDay()])
            ->get();

        foreach ($sevenDay as $sub) {
            $this->sendEmail($sub, '7_day_warning');
            $sent++;
            $this->info("  [7-day] {$sub->tenant?->tenant_id} → {$sub->module?->module_key}");
        }

        // ── 3-day warning ───────────────────────────────────────────────────
        $threeDay = TenantModule::with(['tenant', 'module'])
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->addDays(2)->startOfDay(), now()->addDays(3)->endOfDay()])
            ->get();

        foreach ($threeDay as $sub) {
            $this->sendEmail($sub, '3_day_warning');
            $sent++;
            $this->info("  [3-day] {$sub->tenant?->tenant_id} → {$sub->module?->module_key}");
        }

        // ── Renewal failed alerts ───────────────────────────────────────────
        $renewalFailed = TenantModule::with(['tenant', 'module'])
            ->where('status', 'renewal_failed')
            ->whereNull('notified_renewal_failed_at')   // Don't double-send
            ->get();

        foreach ($renewalFailed as $sub) {
            $this->sendEmail($sub, 'renewal_failed');
            $sub->update(['notified_renewal_failed_at' => now()]);
            $sent++;
            $this->error("  [FAILED] {$sub->tenant?->tenant_id} → {$sub->module?->module_key}");
        }

        // ── Expired (deactivate after grace period of 1 day) ───────────────
        $expired = TenantModule::with(['tenant', 'module'])
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->subDay()) // 1-day grace period
            ->where('auto_renew', false)
            ->get();

        foreach ($expired as $sub) {
            $sub->update(['status' => 'inactive']);
            $this->sendEmail($sub, 'expired');
            $sent++;
            $this->warn("  [EXPIRED] {$sub->tenant?->tenant_id} → {$sub->module?->module_key} deactivated");
        }

        $this->info("Notification run complete — {$sent} email(s) sent.");
        Log::info("Expiry notification run: sent={$sent}");

        return Command::SUCCESS;
    }

    protected function sendEmail(TenantModule $sub, string $type): void
    {
        $tenant = $sub->tenant;
        $module = $sub->module;

        if (!$tenant) return;

        // Find tenant owner's email — assumes Tenant has a 'email' column or relationship
        $email = $tenant->email ?? null;

        if (!$email) {
            Log::warning("No email for tenant {$tenant->tenant_id} — skipping notification type={$type}");
            return;
        }

        $subject  = $this->getSubject($type, $module?->module_name);
        $body     = $this->getBody($type, $tenant, $module, $sub);

        try {
            Mail::raw($body, function ($msg) use ($email, $subject) {
                $msg->to($email)->subject($subject);
            });
            Log::info("Expiry email sent: type={$type} tenant={$tenant->tenant_id} email={$email}");
        } catch (\Exception $e) {
            Log::error("Failed to send expiry email: {$e->getMessage()}");
        }
    }

    protected function getSubject(string $type, ?string $moduleName): string
    {
        return match ($type) {
            '7_day_warning'  => "⚠️ Your {$moduleName} subscription expires in 7 days",
            '3_day_warning'  => "🚨 Your {$moduleName} subscription expires in 3 days",
            'renewal_failed' => "❌ Auto-renewal failed for {$moduleName}",
            'expired'        => "📴 Your {$moduleName} module has been deactivated",
            default          => "Module Subscription Update",
        };
    }

    protected function getBody(string $type, Tenant $tenant, ?object $module, TenantModule $sub): string
    {
        $frontendUrl = config('app.frontend_url', 'https://app.example.com');
        $moduleName  = $module?->module_name ?? 'your module';
        $expiry      = $sub->expires_at?->toFormattedDayDateString() ?? 'soon';

        return match ($type) {
            '7_day_warning', '3_day_warning' => <<<TEXT
Hello {$tenant->name},

Your {$moduleName} subscription will expire on {$expiry}.

To avoid service interruption, please ensure your payment method is up to date.
If auto-renewal is enabled, your card will be charged automatically.

Manage your subscription: {$frontendUrl}/settings/billing

Thank you,
Platform Team
TEXT,
            'renewal_failed' => <<<TEXT
Hello {$tenant->name},

We were unable to charge your saved payment method for the {$moduleName} module renewal.

Please update your payment method as soon as possible to restore access.

Update payment method: {$frontendUrl}/settings/billing

If you require assistance, please contact our support team.

Thank you,
Platform Team
TEXT,
            'expired' => <<<TEXT
Hello {$tenant->name},

Your {$moduleName} subscription has expired and the module has been deactivated.

To restore access, please subscribe again:
{$frontendUrl}/modules

Thank you,
Platform Team
TEXT,
            default => "Your subscription for {$moduleName} has been updated.",
        };
    }
}
