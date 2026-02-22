<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduled Tasks ────────────────────────────────────────────────────────

// Collect database usage stats for all tenants every hour
Schedule::command('tenant:collect-db-stats')->hourly();

// Daily billing enforcement check (overdue invoices → billing_failed)
Schedule::call(function () {
    app(\App\Services\BillingEnforcementService::class)->enforceForAll();
})->dailyAt('00:00');

// Process auto-renewals (runs at 01:00 — charges cards for expiring subscriptions)
Schedule::command('subscriptions:renew')->dailyAt('01:00');

// Send expiry warning + renewal-failed notification emails (runs at 08:00)
Schedule::command('subscriptions:notify-expiry')->dailyAt('08:00');

// IOR: Refresh USD→BDT exchange rate daily at 02:00 AM
// Keeps BDT pricing accurate for new quotes and order calculations
Schedule::command('ior:update-exchange-rate')->dailyAt('02:00');

// IOR: Bulk recalculate pending order prices after exchange rate refresh (02:30 AM)
Schedule::command('ior:recalculate-prices')->dailyAt('02:30');

// IOR: Sync foreign product prices from source marketplaces (03:00 AM)
Schedule::command('ior:sync-products')->dailyAt('03:00');

// IOR: Sync shipment tracking status hourly
// Polling courier APIs (FedEx, DHL, Pathao, etc.) for updates
Schedule::command('ior:sync-tracking')->hourly();

// sGTM: Monitor container health every 5 minutes
// Auto-heals crashed or stopped containers
Schedule::command('sgtm:monitor')->everyFiveMinutes();

// ── Tracking Module: Infrastructure Commands ─────────────────────────────

// Process DLQ retry queue every minute (picks up events past their backoff window)
Schedule::command('tracking:process-retry-queue --batch=50')->everyMinute();

// Generate channel health report every 15 minutes (alerts for degraded channels)
Schedule::command('tracking:health-report --alert-only')->everyFifteenMinutes();

// Expire old DLQ entries daily at 02:30 AM (marks entries > 7 days as expired)
Schedule::command('tracking:expire-dlq --days=7 --purge')->dailyAt('02:30');

// Purge expired consent records daily at 03:30 AM (GDPR compliance)
Schedule::command('tracking:purge-consent')->dailyAt('03:30');
