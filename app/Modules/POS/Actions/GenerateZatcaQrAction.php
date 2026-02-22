<?php

namespace App\Modules\POS\Actions;

/**
 * Generates ZATCA (Saudi Arabia) Phase 1 compliant QR code (TLV Format).
 */
class GenerateZatcaQrAction
{
    public function execute(string $sellerName, string $vatNumber, string $timestamp, float $total, float $vatTotal): string
    {
        $tlv = $this->toTlv(1, $sellerName)
             . $this->toTlv(2, $vatNumber)
             . $this->toTlv(3, $timestamp)
             . $this->toTlv(4, number_format($total, 2, '.', ''))
             . $this->toTlv(5, number_format($vatTotal, 2, '.', ''));

        return base64_encode($tlv);
    }

    private function toTlv($tag, $value): string
    {
        return chr($tag) . chr(strlen($value)) . $value;
    }
}
