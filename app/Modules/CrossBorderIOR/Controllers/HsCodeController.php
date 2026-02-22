<?php

namespace App\Modules\CrossBorderIOR\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LandlordIorHsCode;
use App\Modules\CrossBorderIOR\Services\AiContentService;
use App\Modules\CrossBorderIOR\Services\GlobalDutyService;
use App\Services\SaaSWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HsCodeController extends Controller
{
    public function __construct(
        private SaaSWalletService $wallet,
        private GlobalDutyService $globalDuty,
        private AiContentService $aiService
    ) {}

    /**
     * GET /ior/hs/search
     * Free keyword search across Landlord and Tenant HS tables.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q');
        if (strlen($query) < 3) {
            return response()->json(['success' => true, 'data' => []]);
        }

        // 1. Search in Tenant's Local HS Code Table
        $tenantResults = DB::table('ior_hs_codes')
            ->where('hs_code', 'LIKE', "%{$query}%")
            ->orWhere('category_en', 'LIKE', "%{$query}%")
            ->limit(10)
            ->get()
            ->map(function ($item) {
                $item->source = 'tenant_local';
                return $item;
            });

        // 2. Search in Landlord's Global HS Code Dictionary
        $globalResults = LandlordIorHsCode::where('hs_code', 'LIKE', "%{$query}%")
            ->orWhere('category_en', 'LIKE', "%{$query}%")
            ->limit(10)
            ->get()
            ->map(function ($item) {
                $item->source = 'landlord_global';
                return $item;
            });

        $results = $tenantResults->concat($globalResults)->unique('hs_code')->values();

        return response()->json([
            'success' => true,
            'data'    => $results
        ]);
    }

    /**
     * POST /ior/hs/select
     * Paid selection of an HS code.
     * Implements 24h caching and wallet debiting.
     */
    public function select(Request $request): JsonResponse
    {
        $request->validate([
            'hs_code'             => 'required|string',
            'destination_country' => 'required|string|size:2', // e.g. BD, US
        ]);

        $hsCode    = $request->input('hs_code');
        $country   = strtoupper($request->input('destination_country'));
        $tenantId  = $request->attributes->get('tenant_id');
        
        $cost = config('ior_quotas.costs.hs_lookup', 0.18);

        // 1. Check for 24h cache (Did this tenant pay for this HS + Country recently?)
        $recentLookup = DB::table('ior_hs_lookup_logs')
            ->where('hs_code', $hsCode)
            ->where('destination_country', $country)
            ->where('created_at', '>=', now()->subHours(24))
            ->first();

        if ($recentLookup) {
            // Already paid, fetch and return
            $data = $this->resolveHsData($hsCode, $country);
            return response()->json([
                'success' => true,
                'cached'  => true,
                'data'    => $data,
                'message' => 'Fetched from 24h cache (no charge).'
            ]);
        }

        // 2. Debit Wallet
        $charged = $this->wallet->debit(
            $tenantId,
            $cost,
            'hs_lookup',
            "HS Code lookup premium: {$hsCode} ({$country})",
            $hsCode
        );

        if (!$charged) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance for premium HS lookup.'
            ], 402);
        }

        // 3. Log the paid lookup
        DB::table('ior_hs_lookup_logs')->insert([
            'type'                => 'selection',
            'hs_code'             => $hsCode,
            'destination_country' => $country,
            'cost_usd'            => $cost,
            'source'              => 'premium_select',
            'provider'            => 'zonos',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // 4. Resolve full data (using Zonos or internal)
        $data = $this->resolveHsData($hsCode, $country);

        return response()->json([
            'success' => true,
            'cached'  => false,
            'data'    => $data,
            'message' => "Charged \${$cost} for premium lookup."
        ]);
    }

    /**
     * AI-Assisted HS Code Inference (Paid).
     */
    public function infer(Request $request): JsonResponse
    {
        $request->validate([
            'title'               => 'required|string',
            'description'         => 'nullable|string',
            'destination_country' => 'required|string|size:2',
        ]);

        $title    = $request->input('title');
        $desc     = $request->input('description', '');
        $country  = strtoupper($request->input('destination_country'));
        $tenantId = $request->attributes->get('tenant_id');

        // 1. Abuse-Proof Caching (Content Hashing)
        $inputHash = hash('sha256', strtolower(trim($title)) . $country);
        
        $cached = DB::table('ior_hs_lookup_logs')
            ->where('input_hash', $inputHash)
            ->where('created_at', '>=', now()->subHours(24))
            ->first();

        if ($cached) {
            return response()->json([
                'success' => true,
                'cached'  => true,
                'data'    => json_decode($cached->raw_response, true),
                'message' => 'Fetched from 24h AI cache.'
            ]);
        }

        // 2. Debit Wallet
        $cost = config('ior_quotas.costs.hs_inference', 0.10);
        $charged = $this->wallet->debit(
            $tenantId,
            $cost,
            'hs_inference',
            "AI HS Code inference for: " . substr($title, 0, 50),
            $inputHash
        );

        if (!$charged) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance for AI inference.'
            ], 402);
        }

        // 3. Call AI Inference
        $aiResult = $this->aiService->inferHsCode($title, $desc, $country, $tenantId);

        // 4. Log the transaction
        DB::table('ior_hs_lookup_logs')->insert([
            'type'                => 'inference',
            'input_hash'          => $inputHash,
            'product_name'        => $title,
            'destination_country' => $country,
            'cost_usd'            => $cost,
            'source'              => 'ai_inference',
            'provider'            => $aiResult['provider'],
            'raw_response'        => json_encode($aiResult['data']),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        return response()->json([
            'success' => true,
            'cached'  => false,
            'data'    => $aiResult['data'],
            'message' => "AI inferred HS Codes (Charged \${$cost})."
        ]);
    }

    /**
     * Get lookup history for invoice/billing transparency.
     */
    public function history(Request $request): JsonResponse
    {
        $logs = DB::table('ior_hs_lookup_logs')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $logs
        ]);
    }

    /**
     * Resolve full data for a specific HS code.
     */
    private function resolveHsData(string $hsCode, string $country): array
    {
        // 1. Check Landlord Global Dictionary
        $global = \App\Models\LandlordIorHsCode::where('hs_code', $hsCode)
            ->where('country_code', $country === 'BD' ? 'BGD' : $country)
            ->first();

        if ($global) {
            return [
                'hs_code' => $global->hs_code,
                'category' => $global->category_en,
                'rates' => [
                    'cd' => (float)$global->cd,
                    'rd' => (float)$global->rd,
                    'sd' => (float)$global->sd,
                    'vat' => (float)$global->vat,
                    'ait' => (float)$global->ait,
                    'at' => (float)$global->at,
                ],
                'compliance' => [
                    'is_restricted' => $global->is_restricted,
                    'reason' => $global->restriction_reason
                ],
                'source' => 'global_dictionary'
            ];
        }

        // 2. Fallback to 3rd Party API (Zonos/Hurricane)
        $external = $this->globalDuty->lookup($hsCode, $country);
        return $external ?: [
            'hs_code' => $hsCode,
            'category' => 'Unknown',
            'rates' => null,
            'source' => 'none'
        ];
    }
}
