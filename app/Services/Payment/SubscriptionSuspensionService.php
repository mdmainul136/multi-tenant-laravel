<?php

namespace App\Services\Payment;

use App\Models\TenantModule;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Subscription Grace & Suspension Service.
 *
 * Golden Rule (Middle East SaaS practice):
 *   NEVER auto-charge without user trust.
 *   Prefer wallet-first → card fallback → grace period → soft suspend → hard suspend.
 *
 * Flow after payment failure:
 *   Day 0:    Payment fails → Start grace period (7–14 days) → Email reminder
 *   Day 3:    Retry charge + reminder email
 *   Day 7:    Soft suspend (read-only / limited access) + urgent email
 *   Day 14:   Hard suspend (no access) + final warning
 *   Day 30:   Data retention notice (can reactivate but data may be cleared)
 */
class SubscriptionSuspensionService
{
    const GRACE_DAYS       = 14;
    const SOFT_SUSPEND_DAY = 7;
    const HARD_SUSPEND_DAY = 14;
    const DATA_NOTICE_DAY  = 30;

    /**
     * Called by subscriptions:renew when a renewal charge fails.
     * Starts the grace period timer.
     */
    public function handlePaymentFailure(TenantModule $sub): void
    {
        $gracePeriodEnds = now()->addDays(self::GRACE_DAYS);

        $sub->update([
            'status'               => 'renewal_failed',
            'grace_period_ends_at' => $gracePeriodEnds,
        ]);

        $this->sendEmail($sub, 'payment_failed', [
            'grace_period_ends' => $gracePeriodEnds->toFormattedDayDateString(),
            'days'              => self::GRACE_DAYS,
        ]);

        Log::warning("Grace period started: tenant={$sub->tenant?->tenant_id} module={$sub->module?->module_key} ends={$gracePeriodEnds}");
    }

    /**
     * Called daily by scheduler — enforces the grace → soft → hard pipeline.
     */
    public function enforceSuspensions(): void
    {
        Log::info('Enforcing subscription suspensions...');

        // ── Soft Suspend (Day 7) ───────────────────────────────────────────
        TenantModule::with(['tenant', 'module'])
            ->where('status', 'renewal_failed')
            ->whereNull('soft_suspended_at')
            ->where('grace_period_ends_at', '<=', now()->addDays(self::GRACE_DAYS - self::SOFT_SUSPEND_DAY))
            ->each(fn($sub) => $this->softSuspend($sub));

        // ── Hard Suspend (Day 14) ──────────────────────────────────────────
        TenantModule::with(['tenant', 'module'])
            ->where('status', 'soft_suspended')
            ->whereNotNull('soft_suspended_at')
            ->where('soft_suspended_at', '<=', now()->subDays(self::HARD_SUSPEND_DAY - self::SOFT_SUSPEND_DAY))
            ->each(fn($sub) => $this->hardSuspend($sub));

        // ── Data Retention Warning (Day 30) ───────────────────────────────
        TenantModule::with(['tenant', 'module'])
            ->where('status', 'suspended')
            ->whereNotNull('suspended_at')
            ->where('suspended_at', '<=', now()->subDays(self::DATA_NOTICE_DAY - self::HARD_SUSPEND_DAY))
            ->whereNull('data_notice_sent_at')
            ->each(fn($sub) => $this->sendDataRetentionNotice($sub));

        // ── Day-3 retry charge reminder ────────────────────────────────────
        TenantModule::with(['tenant', 'module'])
            ->where('status', 'renewal_failed')
            ->whereNull('day3_reminder_sent_at')
            ->where('created_at', '<=', now()->subDays(3))
            ->each(fn($sub) => $this->sendDayThreeReminder($sub));

        Log::info('Suspension enforcement complete');
    }

    /**
     * Soft suspend — read-only / limited access (Day 7 of grace).
     */
    public function softSuspend(TenantModule $sub): void
    {
        $sub->update([
            'status'            => 'soft_suspended',
            'soft_suspended_at' => now(),
        ]);

        $this->sendEmail($sub, 'soft_suspended', [
            'days_until_hard' => self::HARD_SUSPEND_DAY - self::SOFT_SUSPEND_DAY,
        ]);

        Log::warning("Soft suspended: tenant={$sub->tenant?->tenant_id} module={$sub->module?->module_key}");
    }

    /**
     * Hard suspend — no access at all (Day 14 of grace).
     */
    public function hardSuspend(TenantModule $sub): void
    {
        $sub->update([
            'status'       => 'suspended',
            'suspended_at' => now(),
        ]);

        $this->sendEmail($sub, 'hard_suspended', [
            'data_notice_days' => self::DATA_NOTICE_DAY - self::HARD_SUSPEND_DAY,
        ]);

        Log::error("Hard suspended: tenant={$sub->tenant?->tenant_id} module={$sub->module?->module_key}");
    }

    /**
     * Reactivate after successful payment.
     * Called by PaymentService after renewal charge succeeds.
     */
    public function reactivate(TenantModule $sub, string $subscriptionType): void
    {
        $newExpiry = match ($subscriptionType) {
            'annual'   => now()->addYear(),
            'lifetime' => null,
            default    => now()->addMonth(),
        };

        $sub->update([
            'status'               => 'active',
            'expires_at'           => $newExpiry,
            'grace_period_ends_at' => null,
            'soft_suspended_at'    => null,
            'suspended_at'         => null,
            'last_renewed_at'      => now(),
        ]);

        $this->sendEmail($sub, 'reactivated');

        Log::info("Reactivated: tenant={$sub->tenant?->tenant_id} module={$sub->module?->module_key}");
    }

    /**
     * Check if a suspended module allows read-only access (soft suspend).
     */
    public function isReadOnly(TenantModule $sub): bool
    {
        return $sub->status === 'soft_suspended';
    }

    // ── Email helpers ──────────────────────────────────────────────────────

    protected function sendDayThreeReminder(TenantModule $sub): void
    {
        $this->sendEmail($sub, 'day3_reminder');
        $sub->update(['day3_reminder_sent_at' => now()]);
    }

    protected function sendDataRetentionNotice(TenantModule $sub): void
    {
        $this->sendEmail($sub, 'data_retention');
        $sub->update(['data_notice_sent_at' => now()]);
    }

    protected function sendEmail(TenantModule $sub, string $type, array $extra = []): void
    {
        $tenant = $sub->tenant;
        $module = $sub->module;
        $email  = $tenant?->email;

        if (!$email) return;

        $frontendUrl = config('app.frontend_url', 'https://app.example.com');
        $moduleName  = $module?->module_name ?? 'your module';

        $subjects = [
            'payment_failed'  => "⚠️ Payment failed — {$moduleName} grace period started",
            'day3_reminder'   => "🔔 3 days since payment failed — update your card",
            'soft_suspended'  => "🟡 {$moduleName} is now in read-only mode",
            'hard_suspended'  => "🔴 {$moduleName} access suspended",
            'data_retention'  => "⏳ Data deletion notice for {$moduleName}",
            'reactivated'     => "✅ {$moduleName} reactivated",
        ];

        $bodies = [
            'payment_failed' => "Your {$moduleName} payment failed. You have {$extra['days']} days grace period until {$extra['grace_period_ends']}.\nUpdate payment: {$frontendUrl}/settings/billing",
            'day3_reminder'  => "It's been 3 days since your {$moduleName} payment failed. Please update your payment method to avoid suspension.\n{$frontendUrl}/settings/billing",
            'soft_suspended' => "Your {$moduleName} is now in read-only mode. Pay within {$extra['days_until_hard']} days to restore full access.\n{$frontendUrl}/settings/billing",
            'hard_suspended' => "Your {$moduleName} has been suspended. Reactivate now: {$frontendUrl}/settings/billing\nYour data will be retained for {$extra['data_notice_days']} more days.",
            'data_retention' => "⚠️ Your {$moduleName} data may be deleted soon. Reactivate your subscription to prevent data loss: {$frontendUrl}/settings/billing",
            'reactivated'    => "✅ Your {$moduleName} has been successfully reactivated. Enjoy full access!",
        ];

        try {
            Mail::raw($bodies[$type] ?? '', fn($m) =>
                $m->to($email)->subject($subjects[$type] ?? 'Subscription Update')
            );
        } catch (\Exception $e) {
            Log::error("Suspension email failed: type={$type} tenant={$tenant?->tenant_id} err={$e->getMessage()}");
        }
    }
}
