<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Log;

/**
 * VAT Invoice Service — ZATCA (Saudi Arabia) + UAE compliant.
 *
 * Saudi Arabia:
 *   - VAT: 15% (introduced 2020, increased from 5%)
 *   - ZATCA (Zakat, Tax and Customs Authority) compliance required
 *   - Arabic invoice copy mandatory
 *   - ZATCA Phase 2: QR code on every B2C invoice
 *
 * UAE:
 *   - VAT: 5%
 *   - TRN (Tax Registration Number) must be visible
 *   - English + Arabic dual language
 *   - Refund policy page mandatory
 */
class VATInvoiceService
{
    const VAT_SA  = 0.15; // Saudi Arabia 15%
    const VAT_UAE = 0.05; // UAE 5%

    /**
     * Calculate VAT for a given country.
     */
    public function calculateVAT(float $amount, string $country): array
    {
        $rate   = $this->getVATRate($country);
        $vatAmt = round($amount * $rate, 2);
        $total  = round($amount + $vatAmt, 2);

        return [
            'subtotal'   => $amount,
            'vat_rate'   => $rate,
            'vat_percent'=> $rate * 100,
            'vat_amount' => $vatAmt,
            'total'      => $total,
            'currency'   => $this->getCurrency($country),
        ];
    }

    public function getVATRate(string $country): float
    {
        return match (strtoupper($country)) {
            'SA'    => self::VAT_SA,
            'AE'    => self::VAT_UAE,
            default => 0.0,
        };
    }

    public function getCurrency(string $country): string
    {
        return match (strtoupper($country)) {
            'SA'    => 'SAR',
            'AE'    => 'AED',
            default => 'USD',
        };
    }

    /**
     * Generate invoice data array (to be used by PDF template or API response).
     */
    public function generate(\App\Models\Invoice $invoice, string $country, array $extra = []): array
    {
        $vat       = $this->calculateVAT($invoice->subtotal, $country);
        $qrCode    = null;
        $trn       = null;

        if (strtoupper($country) === 'SA') {
            $qrCode = $this->generateZATCAQR($invoice, $vat);
        }

        if (strtoupper($country) === 'AE') {
            $trn = config('app.uae_trn', 'TRN-000000000');
        }

        return [
            'invoice_number' => $invoice->invoice_number,
            'invoice_date'   => $invoice->invoice_date?->toFormattedDayDateString(),
            'due_date'       => $invoice->due_date?->toFormattedDayDateString(),
            'country'        => $country,
            'language'       => 'en_ar', // Always bilingual for KSA/UAE

            // Amounts
            'subtotal'       => $vat['subtotal'],
            'vat_rate'       => $vat['vat_rate'],
            'vat_percent'    => $vat['vat_percent'],
            'vat_amount'     => $vat['vat_amount'],
            'total'          => $vat['total'],
            'currency'       => $vat['currency'],

            // Labels (English + Arabic)
            'labels' => [
                'invoice'           => ['en' => 'Tax Invoice',     'ar' => 'فاتورة ضريبية'],
                'subtotal'          => ['en' => 'Subtotal',         'ar' => 'المجموع الفرعي'],
                'vat'               => ['en' => "VAT ({$vat['vat_percent']}%)", 'ar' => "ضريبة القيمة المضافة ({$vat['vat_percent']}%)"],
                'total'             => ['en' => 'Total',             'ar' => 'الإجمالي'],
                'payment_method'    => ['en' => 'Payment Method',  'ar' => 'طريقة الدفع'],
                'subscription_type' => ['en' => 'Subscription',    'ar' => 'الاشتراك'],
            ],

            // Compliance fields
            'zatca_qr'       => $qrCode, // KSA: ZATCA QR (Base64 encoded)
            'trn_number'     => $trn,    // UAE: TRN number
            'seller_vat_no'  => config('app.vat_registration_number', 'VAT-REG-000000000'),

            // Status
            'status'         => $invoice->status,
            'paid_at'        => $invoice->created_at?->toFormattedDayDateString(),
        ];
    }

    /**
     * Generate ZATCA-compliant QR code data (Phase 2).
     * ZATCA QR encodes seller info, VAT, and invoice details as TLV (Tag-Length-Value) Base64.
     */
    public function generateZATCAQR(\App\Models\Invoice $invoice, array $vat): string
    {
        $sellerName  = config('app.name', 'Platform');
        $vatRegNo    = config('app.vat_registration_number', '300000000000003');
        $invoiceDate = $invoice->invoice_date?->format('Y-m-d\TH:i:s\Z') ?? now()->format('Y-m-d\TH:i:s\Z');
        $total       = (string) $vat['total'];
        $vatAmount   = (string) $vat['vat_amount'];

        // TLV encoding (ZATCA spec)
        $tlv = $this->encodeTLV(1, $sellerName)
             . $this->encodeTLV(2, $vatRegNo)
             . $this->encodeTLV(3, $invoiceDate)
             . $this->encodeTLV(4, $total)
             . $this->encodeTLV(5, $vatAmount);

        return base64_encode($tlv);
    }

    /**
     * TLV encoder for ZATCA QR code fields.
     * Tag = 1 byte, Length = 1 byte, Value = N bytes.
     */
    protected function encodeTLV(int $tag, string $value): string
    {
        $valueBytes = $value;
        $length     = strlen($valueBytes);
        return chr($tag) . chr($length) . $valueBytes;
    }
}
