<?php

namespace App\Modules\CrossBorderIOR\Services;

class ProformaInvoiceService
{
    public function __construct(
        private LandedCostCalculatorService $calculator
    ) {}

    /**
     * Generate an itemized cost breakdown for an order.
     */
    public function generateBreakdown(array $orderData): array
    {
        // Use the simulation engine to get precise numbers
        $simulation = $this->calculator.simulate([
            'price_usd' => $orderData['price_usd'],
            'hs_code'   => $orderData['hs_code'] ?? '8471.30.00',
            'weight_kg' => $orderData['weight_kg'] ?? 0.5,
            'dimensions' => $orderData['dimensions'] ?? ['l'=>0,'w'=>0,'h'=>0]
        ]);

        $serviceFeePercent = 10.0; // Default service fee
        $serviceFeeUsd = $simulation['financials']['total_usd'] * ($serviceFeePercent / 100);
        
        $finalTotalUsd = $simulation['financials']['total_usd'] + $serviceFeeUsd;
        $finalTotalBdt = $finalTotalUsd * $simulation['currency']['effective_rate'];

        return array_merge($simulation, [
            'service_fee' => [
                'percent' => $serviceFeePercent,
                'amount_usd' => round($serviceFeeUsd, 2),
            ],
            'grand_total' => [
                'usd' => round($finalTotalUsd, 2),
                'bdt' => ceil($finalTotalBdt)
            ],
            'order_ref' => $orderData['order_ref'] ?? 'IOR-TEMP-' . time()
        ]);
    }

    /**
     * Returns a beautiful HTML template for the pro-forma invoice.
     */
    public function getHtmlTemplate(array $breakdown): string
    {
        $tax = $breakdown['customs']['breakdown'];
        
        return "
        <div style='font-family: sans-serif; max-width: 600px; border: 1px solid #eee; padding: 20px;'>
            <h2 style='color: #2c3e50;'>Pro-forma Invoice (IOR)</h2>
            <p>Order Reference: <strong>{$breakdown['order_ref']}</strong></p>
            <hr>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr style='background: #f8f9fa;'>
                    <th style='text-align: left; padding: 8px;'>Description</th>
                    <th style='text-align: right; padding: 8px;'>Amount (USD)</th>
                </tr>
                <tr>
                    <td style='padding: 8px;'>Marketplace Price</td>
                    <td style='text-align: right; padding: 8px;'>\${$breakdown['input']['price_usd']}</td>
                </tr>
                <tr>
                    <td style='padding: 8px;'>International Shipping ({$breakdown['shipping']['chargeable_weight']}kg)</td>
                    <td style='text-align: right; padding: 8px;'>\${$breakdown['shipping']['total_shipping_usd']}</td>
                </tr>
                <tr>
                    <td style='padding: 8px;'>Bangladesh Customs Duty (HS: {$breakdown['customs']['hs_code']})</td>
                    <td style='text-align: right; padding: 8px;'>\${$breakdown['customs']['total_tax_usd']}</td>
                </tr>
                <tr>
                    <td style='padding: 8px; font-size: 0.8em; color: #666;'>
                        (CD: \${$tax['cd']}, RD: \${$tax['rd']}, SD: \${$tax['sd']}, VAT: \${$tax['vat']}, AIT: \${$tax['ait']}, AT: \${$tax['at']})
                    </td>
                    <td></td>
                </tr>
                <tr>
                    <td style='padding: 8px;'>Service Fee ({$breakdown['service_fee']['percent']}%)</td>
                    <td style='text-align: right; padding: 8px;'>\${$breakdown['service_fee']['amount_usd']}</td>
                </tr>
                <tr style='font-weight: bold; border-top: 2px solid #333;'>
                    <td style='padding: 8px;'>Total Landed Cost</td>
                    <td style='text-align: right; padding: 8px;'>\${$breakdown['grand_total']['usd']}</td>
                </tr>
            </table>

            <div style='margin-top: 20px; background: #eef2f7; padding: 15px; border-radius: 5px;'>
                <p style='margin:0;'>Applied Exchange Rate: <strong>{$breakdown['currency']['effective_rate']} BDT/USD</strong></p>
                <h3 style='margin: 10px 0 0 0; color: #2980b9;'>Payable Total: ৳" . number_format($breakdown['grand_total']['bdt']) . "</h3>
            </div>
            
            <p style='font-size: 0.7em; color: #999; margin-top: 20px;'>
                * This is an automated landing cost estimation. Final values may vary slightly based on customs assessment at HQ.
            </p>
        </div>
        ";
    }
}
