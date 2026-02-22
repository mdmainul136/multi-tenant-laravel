<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\SaaSWalletService;
use App\Modules\CrossBorderIOR\Services\GlobalDutyService;

class CustomsDutyService
{
    protected SaaSWalletService $walletService;
    protected GlobalDutyService $globalDutyService;

    public function __construct(SaaSWalletService $walletService, GlobalDutyService $globalDutyService)
    {
        $this->walletService = $walletService;
        $this->globalDutyService = $globalDutyService;
    }

    /**
     * Calculate total landed cost and tax breakdown for a product.
     * 
     * Formula based on Bangladesh Customs rules (or dynamic based on country):
     * AV (Assessable Value) = (Price + Insurance + Freight)
     */
    public function calculate(float $priceUsd, string $hsCode, float $freightUsd = 0, float $insuranceUsd = 0, string $tenantId = null): array
    {
        // 1. Try Tenant Local HS Codes
        try {
            $hs = DB::table('ior_hs_codes')->where('hs_code', $hsCode)->first();
        } catch (\Exception $e) {
            $hs = null;
        }

        // 2. Fallback to Landlord Global Dictionary
        if (!$hs) {
            try {
                $hs = \App\Models\LandlordIorHsCode::where('hs_code', $hsCode)->first();
                if ($hs) {
                    $hs->source = 'global_db';
                }
            } catch (\Exception $e) {
                Log::warning("Global HS Code lookup failed: " . $e->getMessage());
            }
        } else {
            $hs->source = 'tenant';
        }

        // 3. Fallback to Premium Selection Cache (ior_hs_lookup_logs)
        if (!$hs && $tenantId) {
            $cached = DB::table('ior_hs_lookup_logs')
                ->where('hs_code', $hsCode)
                ->where('created_at', '>=', now()->subHours(24))
                ->first();

            if ($cached) {
                // Re-resolve data if cached log exists
                $hs = (object) $this->globalDutyService->lookup($hsCode, 'BD'); // BD as per BD customs rules context
                if ($hs) {
                    $hs->source = 'cache_24h';
                }
            }
        }

        // 4. Fallback to External Global APIs (Chargeable - legacy path)
        if (!$hs && $tenantId) {
            $globalResult = $this->globalDutyService->lookup($hsCode, 'BD'); 
            
            if ($globalResult) {
                // Charge the wallet for premium lookup
                $cost = config('ior_quotas.costs.hs_lookup', 0.18);
                $charged = $this->walletService->debit(
                    $tenantId, 
                    $cost, 
                    'customs_lookup', 
                    "HS Code lookup select: {$hsCode}", 
                    $hsCode
                );

                if ($charged) {
                    $hs = (object) $globalResult;
                    $hs->source = 'external_api';

                    // Log it for cache
                    DB::table('ior_hs_lookup_logs')->insert([
                        'hs_code'             => $hsCode,
                        'destination_country' => 'BD',
                        'cost_usd'            => $cost,
                        'source'              => 'customs_duty_service',
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);
                }
            }
        }
        
        if (!$hs) {
            // Fallback to default general rates
            $hs = (object) [
                'cd' => 25.0,
                'rd' => 3.0,
                'sd' => 0.0,
                'vat' => 15.0,
                'ait' => 5.0,
                'at' => 5.0,
                'source' => 'system_fallback'
            ];
        }

        $av = $priceUsd + $freightUsd + $insuranceUsd;
        
        $cdAmount  = $av * ($hs->cd / 100);
        $rdAmount  = $av * ($hs->rd / 100);
        $baseForSd = $av + $cdAmount + $rdAmount;
        $sdAmount  = $baseForSd * ($hs->sd / 100);
        
        $baseForVat = $baseForSd + $sdAmount;
        $vatAmount  = $baseForVat * ($hs->vat / 100);
        
        $aitAmount = $av * ($hs->ait / 100);
        $atAmount  = $baseForVat * ($hs->at / 100);

        $totalTaxUsd = $cdAmount + $rdAmount + $sdAmount + $vatAmount + $aitAmount + $atAmount;

        return [
            'hs_code'        => $hsCode,
            'source'         => $hs->source ?? 'unknown',
            'assessable_value' => round($av, 2),
            'breakdown' => [
                'cd'  => round($cdAmount, 2),
                'rd'  => round($rdAmount, 2),
                'sd'  => round($sdAmount, 2),
                'vat' => round($vatAmount, 2),
                'ait' => round($aitAmount, 2),
                'at'  => round($atAmount, 2),
            ],
            'total_tax_usd'  => round($totalTaxUsd, 2),
            'landed_cost_usd' => round($av + $totalTaxUsd, 2),
            'tax_percentage_effective' => round(($totalTaxUsd / $av) * 100, 2)
        ];
    }

    /**
     * Seeds common HS codes for testing.
     */
    public function seedCommonCodes(): void
    {
        $codes = [
            [
                'hs_code' => '8471.30.00',
                'category_en' => 'Laptops/Computers',
                'cd' => 0, 'rd' => 0, 'sd' => 0, 'vat' => 15, 'ait' => 5, 'at' => 5,
                'is_restricted' => false
            ],
            [
                'hs_code' => '8517.13.00',
                'category_en' => 'Smartphones',
                'cd' => 25, 'rd' => 3, 'sd' => 0, 'vat' => 15, 'ait' => 5, 'at' => 5,
                'is_restricted' => false
            ],
            [
                'hs_code' => '3304.99.00',
                'category_en' => 'Cosmetics (Skincare)',
                'cd' => 25, 'rd' => 3, 'sd' => 20, 'vat' => 15, 'ait' => 5, 'at' => 5,
                'is_restricted' => false
            ],
            [
                'hs_code' => '6204.00.00',
                'category_en' => 'Clothing (Women)',
                'cd' => 25, 'rd' => 3, 'sd' => 0, 'vat' => 15, 'ait' => 5, 'at' => 5,
                'is_restricted' => false
            ]
        ];

        foreach ($codes as $code) {
            DB::table('ior_hs_codes')->updateOrInsert(['hs_code' => $code['hs_code']], $code);
        }
    }
}
