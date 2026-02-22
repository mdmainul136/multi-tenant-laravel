<?php

namespace App\Modules\CrossBorderIOR\Services;

use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Models\CrossBorderIOR\IorSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * OrderNotificationService
 *
 * Sends order lifecycle emails via Laravel Mail (SMTP configured in .env).
 * Mirrors Supabase `send-order-notification` function.
 *
 * Statuses handled:
 *   confirmed  → order placed / payment received
 *   shipped    → tracking number assigned
 *   delivered  → carrier confirms delivery
 *   cancelled  → order cancelled
 */
class OrderNotificationService
{
    private string $senderName;
    private string $senderEmail;

    public function __construct()
    {
        $this->senderName  = IorSetting::get('admin_notification_email', config('mail.from.name'))  ?? 'IOR Store';
        $this->senderEmail = IorSetting::get('support_email',            config('mail.from.address')) ?? 'noreply@example.com';
    }

    // ──────────────────────────────────────────────────────────────
    // PUBLIC API
    // ──────────────────────────────────────────────────────────────

    /** Send order confirmation after payment. */
    public function sendConfirmation(IorForeignOrder $order): bool
    {
        return $this->send($order, 'confirmed', [
            'title'       => '✅ আপনার অর্ডার নিশ্চিত হয়েছে!',
            'title_en'    => 'Your Order is Confirmed!',
            'description' => 'আমরা আপনার অর্ডার পেয়েছি এবং প্রসেস শুরু করেছি।',
            'emoji'       => '✅',
            'color'       => '#22c55e',
        ]);
    }

    /** Send shipping notification with tracking info. */
    public function sendShipped(IorForeignOrder $order): bool
    {
        return $this->send($order, 'shipped', [
            'title'       => '🚚 আপনার পণ্য পাঠানো হয়েছে!',
            'title_en'    => 'Your Order Has Been Shipped!',
            'description' => 'আপনার পণ্য রওনা হয়েছে। ট্র্যাকিং নম্বর দিয়ে ট্র্যাক করুন।',
            'emoji'       => '🚚',
            'color'       => '#6366f1',
        ]);
    }

    /** Send delivery confirmation. */
    public function sendDelivered(IorForeignOrder $order): bool
    {
        return $this->send($order, 'delivered', [
            'title'       => '📦 পণ্য ডেলিভারি হয়েছে!',
            'title_en'    => 'Your Order Has Been Delivered!',
            'description' => 'আপনার পণ্য সফলভাবে ডেলিভারি হয়েছে। ধন্যবাদ!',
            'emoji'       => '📦',
            'color'       => '#f59e0b',
        ]);
    }

    /** Send cancellation notice. */
    public function sendCancelled(IorForeignOrder $order, string $reason = ''): bool
    {
        return $this->send($order, 'cancelled', [
            'title'       => '❌ অর্ডার বাতিল করা হয়েছে',
            'title_en'    => 'Order Cancelled',
            'description' => $reason ?: 'আপনার অর্ডারটি বাতিল করা হয়েছে।',
            'emoji'       => '❌',
            'color'       => '#ef4444',
        ]);
    }

    /**
     * Notify customer when order arrives at international warehouse.
     */
    public function sendWarehouseArrival(IorForeignOrder $order): bool
    {
        return $this->send($order, 'warehouse_arrival', [
            'title'       => '📦 আপনার পণ্য আমাদের ওয়্যারহাউসে পৌঁছেছে!',
            'title_en'    => 'Your Order Has Arrived at Warehouse!',
            'description' => 'আপনার পণ্য আমাদের আন্তর্জাতিক ওয়্যারহাউসে পৌঁছেছে এবং এখন শিপিংয়ের জন্য প্রস্তুত হচ্ছে।',
            'emoji'       => '📦',
            'color'       => '#f59e0b', // Using a similar color to delivered for now, can be changed
        ]);
    }
    /**
     * Notify customer when order is dispatched from warehouse.
     */
    public function sendDispatched(IorForeignOrder $order): bool
    {
        return $this->send($order, 'dispatched', [
            'title'       => '✈️ আপনার পণ্য পাঠানো হয়েছে!',
            'title_en'    => 'Your Order Has Been Dispatched!',
            'description' => 'আপনার পণ্য আমাদের ওয়্যারহাউস থেকে পাঠানো হয়েছে এবং আপনার দেশের দিকে রওনা হয়েছে।',
            'emoji'       => '✈️',
            'color'       => '#3b82f6',
        ]);
    }

    /**
     * Notify customer when order clears customs.
     */
    public function sendCustomsCleared(IorForeignOrder $order): bool
    {
        return $this->send($order, 'customs_cleared', [
            'title'       => '✅ আপনার পণ্য কাস্টমস থেকে ছাড় পেয়েছে!',
            'title_en'    => 'Your Order Has Cleared Customs!',
            'description' => 'আপনার পণ্য সফলভাবে কাস্টমস ক্লিয়ার হয়েছে এবং শীঘ্রই ডেলিভারি হবে।',
            'emoji'       => '✅',
            'color'       => '#10b981',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // INTERNAL
    // ──────────────────────────────────────────────────────────────

    private function send(IorForeignOrder $order, string $status, array $meta): bool
    {
        if (empty($order->customer_email)) {
            Log::warning("[IOR Notify] Order {$order->order_number} has no customer_email — skipping.");
            return false;
        }

        try {
            $html = $this->buildHtml($order, $status, $meta);

            $subjectMap = [
                'confirmed' => "✅ Order {$order->order_number} Confirmed | IOR",
                'shipped'   => "🚚 Order {$order->order_number} Shipped | IOR",
                'delivered' => "📦 Order {$order->order_number} Delivered | IOR",
                'cancelled' => "❌ Order {$order->order_number} Cancelled | IOR",
            ];
            $subject = $subjectMap[$status] ?? "Order Update: {$order->order_number}";

            Mail::html($html, function ($message) use ($order, $subject) {
                $message->to($order->customer_email, $order->customer_name ?? 'Customer')
                        ->subject($subject)
                        ->from($this->senderEmail, $this->senderName);
            });

            // Log to ior_logs
            \DB::table('ior_logs')->insert([
                'order_id'   => $order->id,
                'event'      => "email_{$status}",
                'payload'    => json_encode(['to' => $order->customer_email, 'subject' => $subject]),
                'status'     => 'sent',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info("[IOR Notify] ✅ Sent {$status} email → {$order->customer_email} for order {$order->order_number}");
            return true;
        } catch (\Exception $e) {
            Log::error("[IOR Notify] ❌ Failed to send {$status} email for order {$order->order_number}: " . $e->getMessage());
            return false;
        }
    }

    // ──────────────────────────────────────────────────────────────
    // HTML TEMPLATE
    // ──────────────────────────────────────────────────────────────

    private function buildHtml(IorForeignOrder $order, string $status, array $meta): string
    {
        $orderNumber    = $order->order_number;
        $customerName   = $order->customer_name ?? 'Customer';
        $productName    = $order->product_name  ?? 'Your Product';
        $totalBdt       = '৳' . number_format((float) ($order->total_bdt ?? 0), 0);
        $shippingAddr   = implode(', ', array_filter([
            $order->shipping_address ?? '',
            $order->shipping_city    ?? '',
        ]));
        $trackingNumber = $order->tracking_number ?? null;
        $courierCode    = strtoupper($order->courier_code ?? '');

        $trackingBlock  = $trackingNumber
            ? "<div style='background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:16px;margin:16px 0;'>
                <p style='margin:0 0 4px;font-size:12px;color:#0284c7;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;'>TRACKING</p>
                <p style='margin:0;font-size:18px;font-weight:700;color:#0c4a6e;'>{$trackingNumber}</p>
                <p style='margin:4px 0 0;font-size:12px;color:#64748b;'>Carrier: {$courierCode}</p>
              </div>"
            : '';

        $addrBlock = $shippingAddr
            ? "<div style='background:#f8fafc;border-radius:8px;padding:12px;margin:12px 0;'>
                <p style='margin:0 0 4px;font-size:12px;color:#64748b;'>Shipping to</p>
                <p style='margin:0;font-size:14px;color:#1e293b;'>{$shippingAddr}</p>
              </div>"
            : '';

        $frontendUrl    = env('FRONTEND_URL', 'https://example.com');
        $invoiceLink    = "{$frontendUrl}/ior/invoice/{$order->id}";

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Update</title>
</head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;background-color:#f4f4f5;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5;padding:40px 20px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">

        <!-- Header -->
        <tr><td style="background:linear-gradient(135deg,{$meta['color']},{$meta['color']}dd);padding:32px;text-align:center;">
          <p style="margin:0 0 8px;font-size:40px;">{$meta['emoji']}</p>
          <h1 style="color:#ffffff;margin:0;font-size:22px;font-weight:700;">{$meta['title_en']}</h1>
        </td></tr>

        <!-- Body -->
        <tr><td style="padding:32px;">
          <p style="margin:0 0 20px;color:#374151;font-size:16px;">প্রিয় <strong>{$customerName}</strong>,</p>
          <p style="margin:0 0 20px;color:#6b7280;font-size:14px;">{$meta['description']}</p>

          <!-- Order Box -->
          <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin-bottom:20px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td>
                  <p style="margin:0 0 4px;font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;">Order Number</p>
                  <p style="margin:0;font-size:20px;font-weight:700;color:#111827;">{$orderNumber}</p>
                </td>
                <td style="text-align:right;">
                  <p style="margin:0 0 4px;font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;">Total</p>
                  <p style="margin:0;font-size:20px;font-weight:700;color:{$meta['color']};">{$totalBdt}</p>
                </td>
              </tr>
            </table>
            <hr style="border:none;border-top:1px solid #e5e7eb;margin:16px 0;">
            <p style="margin:0 0 4px;font-size:12px;color:#9ca3af;">Product</p>
            <p style="margin:0;font-size:14px;font-weight:600;color:#374151;">{$productName}</p>
            {$addrBlock}
          </div>

          {$trackingBlock}

          <!-- CTA Button -->
          <div style="text-align:center;margin:24px 0 16px;">
            <a href="{$invoiceLink}" style="display:inline-block;background:{$meta['color']};color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:10px;font-weight:700;font-size:14px;">
              View Invoice
            </a>
          </div>
        </td></tr>

        <!-- Footer -->
        <tr><td style="padding:20px 32px;background-color:#f9fafb;text-align:center;border-top:1px solid #f3f4f6;">
          <p style="margin:0;color:#9ca3af;font-size:12px;">এই ইমেইল স্বয়ংক্রিয়ভাবে পাঠানো হয়েছে। ধন্যবাদ শপিং করার জন্য!</p>
          <p style="margin:4px 0 0;color:#d1d5db;font-size:11px;">IOR Cross-Border Import Service</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }
}



