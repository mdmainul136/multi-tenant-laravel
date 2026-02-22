<?php

namespace App\Modules\CrossBorderIOR\Services;

use App\Models\CrossBorderIOR\IorSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * ExchangeRateNotificationService
 *
 * Sends an admin email notification after the exchange rate is refreshed,
 * reporting the new rate, how many orders/products were updated, and any errors.
 *
 * Ported from Supabase `send-exchange-rate-notification`.
 * Uses Laravel Mail (SMTP) instead of Resend API.
 */
class ExchangeRateNotificationService
{
    public function notify(
        float  $newRate,
        float  $previousRate,
        int    $ordersUpdated = 0,
        int    $totalOrders   = 0,
        array  $errors        = [],
        string $triggeredBy   = 'cron',
    ): bool {
        $adminEmail = IorSetting::get('admin_notification_email')
            ?? config('mail.from.address')
            ?? env('ADMIN_EMAIL');

        if (!$adminEmail) {
            Log::info('[IOR ExchangeNotify] No admin_notification_email configured, skipping.');
            return false;
        }

        try {
            $html = $this->buildHtml($newRate, $previousRate, $ordersUpdated, $totalOrders, $errors);

            $changePct   = $previousRate > 0
                ? round((($newRate - $previousRate) / $previousRate) * 100, 3)
                : 0;
            $statusEmoji = count($errors) > 0 ? '⚠️' : '✅';
            $subject     = "{$statusEmoji} IOR Exchange Rate Update: ৳{$newRate} — {$ordersUpdated} orders recalculated";

            Mail::html($html, function ($message) use ($adminEmail, $subject) {
                $message->to($adminEmail)
                        ->subject($subject)
                        ->from(config('mail.from.address'), config('mail.from.name'));
            });

            Log::info("[IOR ExchangeNotify] ✅ Notification sent → {$adminEmail} | rate={$newRate} | orders={$ordersUpdated}");
            return true;
        } catch (\Exception $e) {
            Log::error('[IOR ExchangeNotify] Failed: ' . $e->getMessage());
            return false;
        }
    }

    // ──────────────────────────────────────────────────────────────
    // HTML Template
    // ──────────────────────────────────────────────────────────────

    private function buildHtml(
        float $newRate,
        float $previousRate,
        int   $ordersUpdated,
        int   $totalOrders,
        array $errors,
    ): string {
        $changePct    = $previousRate > 0
            ? round((($newRate - $previousRate) / $previousRate) * 100, 3)
            : 0;
        $changeDir    = $changePct >= 0 ? '▲' : '▼';
        $changeColor  = $changePct >= 0 ? '#dc2626' : '#16a34a'; // red if BDT went up (worse for buyers)
        $hasErrors    = count($errors) > 0;
        $statusEmoji  = $hasErrors ? '⚠️' : '✅';
        $statusText   = $hasErrors ? 'Completed with Errors' : 'Successful';
        $timestamp    = now()->format('D, d M Y H:i') . ' BDT';

        $errorSection = '';
        if ($hasErrors) {
            $items = collect($errors)->take(5)->map(fn($e) => "<li style='font-size:12px;color:#7f1d1d'>{$e}</li>")->implode('');
            $more  = count($errors) > 5 ? "<li>...and " . (count($errors) - 5) . " more</li>" : '';
            $errorSection = "<div style='background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:15px;margin-top:20px;'><h3 style='color:#dc2626;margin:0 0 10px;font-size:14px;'>⚠️ Errors Encountered</h3><ul style='margin:0;padding-left:20px;'>{$items}{$more}</ul></div>";
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;background:#f4f4f5;">
  <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 20px;background:#f4f4f5;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.08);">

        <!-- Header -->
        <tr><td style="background:linear-gradient(135deg,#10b981,#059669);padding:30px;text-align:center;">
          <h1 style="color:#fff;margin:0;font-size:22px;font-weight:700;">
            Exchange Rate Update {$statusEmoji}
          </h1>
          <p style="color:#d1fae5;margin:6px 0 0;font-size:13px;">{$timestamp}</p>
        </td></tr>

        <!-- Stats -->
        <tr><td style="padding:30px;">
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
            <tr>
              <td width="33%" style="padding:0 6px 0 0;">
                <div style="background:#f0fdf4;border-radius:10px;padding:16px;text-align:center;">
                  <p style="margin:0;color:#166534;font-size:26px;font-weight:700;">৳{$newRate}</p>
                  <p style="margin:4px 0 0;color:#15803d;font-size:11px;text-transform:uppercase;letter-spacing:.05em;">New Rate (1 USD)</p>
                </div>
              </td>
              <td width="33%" style="padding:0 3px;">
                <div style="background:#fff7ed;border-radius:10px;padding:16px;text-align:center;">
                  <p style="margin:0;color:{$changeColor};font-size:26px;font-weight:700;">{$changeDir} {$changePct}%</p>
                  <p style="margin:4px 0 0;color:#9a3412;font-size:11px;text-transform:uppercase;letter-spacing:.05em;">vs. Previous ৳{$previousRate}</p>
                </div>
              </td>
              <td width="33%" style="padding:0 0 0 6px;">
                <div style="background:#eff6ff;border-radius:10px;padding:16px;text-align:center;">
                  <p style="margin:0;color:#1d4ed8;font-size:26px;font-weight:700;">{$ordersUpdated}/{$totalOrders}</p>
                  <p style="margin:4px 0 0;color:#2563eb;font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Orders Recalculated</p>
                </div>
              </td>
            </tr>
          </table>

          <!-- Detail row -->
          <div style="background:#f9fafb;border-radius:8px;padding:14px;margin-bottom:16px;">
            <table width="100%" cellpadding="4" cellspacing="0">
              <tr>
                <td style="color:#6b7280;font-size:13px;">Status</td>
                <td style="color:#111827;font-size:13px;font-weight:600;text-align:right;">{$statusText}</td>
              </tr>
              <tr>
                <td style="color:#6b7280;font-size:13px;">Triggered by</td>
                <td style="color:#111827;font-size:13px;text-align:right;">{$timestamp}</td>
              </tr>
            </table>
          </div>

          {$errorSection}
        </td></tr>

        <!-- Footer -->
        <tr><td style="padding:16px 30px;background:#f9fafb;text-align:center;border-top:1px solid #f3f4f6;">
          <p style="margin:0;color:#9ca3af;font-size:11px;">Automated IOR pricing system notification. Do not reply to this email.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }
}



