<?php

namespace App\Modules\CrossBorderIOR\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Models\CrossBorderIOR\IorSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * GET /ior/invoices/{id}
     * Generate and return HTML invoice for an IOR order.
     */
    public function show(int $id): JsonResponse
    {
        $order = IorForeignOrder::with('user')->findOrFail($id);
        $brand = IorSetting::allAsMap('general');

        $html = $this->generateInvoiceHtml($order, $brand);

        return response()->json([
            'success' => true,
            'data' => [
                'order_number' => $order->order_number,
                'html'         => $html,
            ],
        ]);
    }

    /**
     * GET /ior/invoices/{id}/download
     * Return raw HTML for browser print/download.
     */
    public function download(int $id)
    {
        $order = IorForeignOrder::with('user')->findOrFail($id);
        $brand = IorSetting::allAsMap('general');
        $html  = $this->generateInvoiceHtml($order, $brand);

        return response($html, 200, [
            'Content-Type'        => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="invoice-' . $order->order_number . '.html"',
        ]);
    }

    private function generateInvoiceHtml(IorForeignOrder $order, array $brand): string
    {
        $storeName    = $brand['store_name']    ?? 'IOR Store';
        $storeAddress = $brand['store_address'] ?? '';
        $supportEmail = $brand['support_email'] ?? '';
        $supportPhone = $brand['support_phone'] ?? '';

        $finalPrice   = $order->final_price_bdt ?? $order->estimated_price_bdt ?? 0;
        $advance      = $order->advance_amount   ?? 0;
        $remaining    = $order->remaining_amount ?? 0;
        $paidBadge    = $order->advance_paid ? ($order->remaining_paid ? '✓ Fully Paid' : '✓ Advance Paid') : 'Pending Payment';
        $paidColor    = $order->advance_paid ? '#16a34a' : '#d97706';
        $orderDate    = $order->created_at->format('d M Y');

        $customerName    = $order->customer_name;
        $customerPhone   = $order->shipping_phone ?? '';
        $customerAddress = trim(($order->shipping_address ?? '') . ', ' . ($order->shipping_city ?? ''), ', ');
        $marketplace     = ucfirst($order->source_marketplace ?? 'Online');
        $paymentMethod   = match ($order->payment_method) {
            'bkash'      => 'bKash',
            'sslcommerz' => 'SSLCommerz',
            'stripe'     => 'Card (Stripe)',
            'cod'        => 'Cash on Delivery',
            default      => $order->payment_method ?? 'N/A',
        };

        $bdtFmt = fn(float $n) => '৳' . number_format($n, 0, '.', ',');

        // Items table
        $usdPrice = $order->source_price_usd ? ('$' . number_format($order->source_price_usd, 2)) : '—';
        $itemRow  = "
            <tr>
                <td style='padding:12px;border-bottom:1px solid #e5e7eb;'>
                    <div style='font-weight:600;'>" . e($order->product_name) . "</div>
                    " . ($order->product_variant ? "<div style='font-size:12px;color:#6b7280;'>Variant: " . e($order->product_variant) . "</div>" : '') . "
                    <div style='font-size:12px;color:#6b7280;'>Source: $marketplace | Original: $usdPrice</div>
                </td>
                <td style='padding:12px;border-bottom:1px solid #e5e7eb;text-align:center;'>$order->quantity</td>
                <td style='padding:12px;border-bottom:1px solid #e5e7eb;text-align:right;'>{$bdtFmt($finalPrice / max(1, $order->quantity))}</td>
                <td style='padding:12px;border-bottom:1px solid #e5e7eb;text-align:right;'>{$bdtFmt($finalPrice)}</td>
            </tr>";

        // Pricing breakdown
        $pbRows = '';
        if ($order->base_price_bdt) {
            $pbRows .= "<tr><td style='color:#6b7280;'>Base Price (USD × Rate)</td><td style='text-align:right;'>{$bdtFmt($order->base_price_bdt)}</td></tr>";
        }
        if ($order->customs_fee_bdt) {
            $pbRows .= "<tr><td style='color:#6b7280;'>Customs Duty</td><td style='text-align:right;'>{$bdtFmt($order->customs_fee_bdt)}</td></tr>";
        }
        if ($order->shipping_cost_bdt) {
            $pbRows .= "<tr><td style='color:#6b7280;'>Int'l Shipping</td><td style='text-align:right;'>{$bdtFmt($order->shipping_cost_bdt)}</td></tr>";
        }
        if ($order->profit_margin_bdt) {
            $pbRows .= "<tr><td style='color:#6b7280;'>Service Fee</td><td style='text-align:right;'>{$bdtFmt($order->profit_margin_bdt)}</td></tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Invoice #{$order->order_number}</title>
  <style>
    @media print { body { -webkit-print-color-adjust:exact; print-color-adjust:exact; } }
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:#1f2937; line-height:1.5; }
    table { border-collapse:collapse; }
  </style>
</head>
<body style="padding:40px;max-width:800px;margin:0 auto;background:#fff;">

  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:32px;border-bottom:2px solid #3b82f6;padding-bottom:20px;">
    <div>
      <h1 style="font-size:24px;font-weight:800;color:#3b82f6;">{$storeName}</h1>
      <div style="font-size:13px;color:#6b7280;margin-top:6px;">
        {$storeAddress}<br>
        {$supportPhone} | {$supportEmail}
      </div>
    </div>
    <div style="text-align:right;">
      <h2 style="font-size:22px;font-weight:700;">INVOICE</h2>
      <div style="font-size:13px;margin-top:6px;">
        <div><strong>#:</strong> {$order->order_number}</div>
        <div><strong>Date:</strong> {$orderDate}</div>
        <div style="margin-top:8px;display:inline-block;padding:3px 12px;border-radius:999px;font-size:12px;font-weight:600;background:#dcfce7;color:{$paidColor};">{$paidBadge}</div>
      </div>
    </div>
  </div>

  <!-- Customer & Order Info -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-bottom:32px;">
    <div>
      <h3 style="font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;margin-bottom:10px;">Deliver To</h3>
      <div style="font-weight:700;">{$customerName}</div>
      <div>{$customerAddress}</div>
      <div>{$customerPhone}</div>
    </div>
    <div>
      <h3 style="font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;margin-bottom:10px;">Order Info</h3>
      <div><strong>Type:</strong> Cross-Border / IOR</div>
      <div><strong>Marketplace:</strong> {$marketplace}</div>
      <div><strong>Payment:</strong> {$paymentMethod}</div>
      <div><strong>Status:</strong> {$order->order_status}</div>
      {$this->trackingRow($order)}
    </div>
  </div>

  <!-- Items Table -->
  <table style="width:100%;margin-bottom:24px;">
    <thead>
      <tr style="background:#f3f4f6;">
        <th style="padding:12px;text-align:left;font-size:13px;border-bottom:2px solid #e5e7eb;">Product</th>
        <th style="padding:12px;text-align:center;font-size:13px;border-bottom:2px solid #e5e7eb;">Qty</th>
        <th style="padding:12px;text-align:right;font-size:13px;border-bottom:2px solid #e5e7eb;">Unit Price</th>
        <th style="padding:12px;text-align:right;font-size:13px;border-bottom:2px solid #e5e7eb;">Total</th>
      </tr>
    </thead>
    <tbody>{$itemRow}</tbody>
  </table>

  <!-- Pricing Breakdown + Totals -->
  <div style="display:flex;justify-content:flex-end;">
    <div style="width:320px;">
      <table style="width:100%;font-size:14px;">
        <tbody>
          {$pbRows}
          <tr style="font-weight:700;font-size:16px;border-top:2px solid #1f2937;">
            <td style="padding-top:10px;">Total</td>
            <td style="padding-top:10px;text-align:right;color:#3b82f6;">{$bdtFmt($finalPrice)}</td>
          </tr>
          <tr><td style="color:#6b7280;padding-top:6px;">Advance Paid (50%)</td><td style="text-align:right;color:#16a34a;padding-top:6px;">{$bdtFmt($advance)}</td></tr>
          <tr><td style="color:#6b7280;">Remaining Due</td><td style="text-align:right;color:#dc2626;">{$bdtFmt($remaining)}</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Footer -->
  <div style="margin-top:48px;padding-top:16px;border-top:1px solid #e5e7eb;text-align:center;color:#6b7280;font-size:13px;">
    <p>Thank you for your order! Questions? Email us at {$supportEmail}</p>
    <p style="margin-top:6px;font-size:11px;">Exchange rate at order time: 1 USD = {$order->exchange_rate} BDT</p>
  </div>

</body>
</html>
HTML;
    }

    private function trackingRow(IorForeignOrder $order): string
    {
        if (!$order->tracking_number) return '';
        return "<div><strong>Tracking:</strong> {$order->tracking_number} ({$order->courier_code})</div>";
    }
}



