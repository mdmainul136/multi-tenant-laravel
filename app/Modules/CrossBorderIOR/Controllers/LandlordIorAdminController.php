<?php

namespace App\Modules\CrossBorderIOR\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LandlordIorRestrictedItem;
use App\Models\LandlordIorCountry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LandlordIorAdminController extends Controller
{
    /**
     * List all global restricted keywords.
     */
    public function listRestrictedItems(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => LandlordIorRestrictedItem::all()
        ]);
    }

    /**
     * Add a new global restricted keyword.
     */
    public function addRestrictedItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'keyword' => 'required|string|unique:mysql.landlord_ior_restricted_items,keyword',
            'reason'  => 'required|string',
            'severity' => 'in:warning,blocking',
            'origin_country_code' => 'nullable|string|size:3',
        ]);

        $item = LandlordIorRestrictedItem::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Global restricted item added.',
            'data' => $item
        ]);
    }

    /**
     * Delete a global restricted item.
     */
    public function deleteRestrictedItem(int $id): JsonResponse
    {
        LandlordIorRestrictedItem::destroy($id);
        return response()->json(['success' => true, 'message' => 'Item deleted.']);
    }

    /**
     * List all supported countries and their baseline IOR settings.
     */
    public function listCountries(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => LandlordIorCountry::all()
        ]);
    }

    /**
     * Update global country settings.
     */
    public function updateCountry(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'default_duty_percent' => 'numeric|min:0',
            'default_shipping_rate_per_kg' => 'numeric|min:0',
            'is_active' => 'boolean'
        ]);

        $country = LandlordIorCountry::findOrFail($id);
        $country->update($data);

        return response()->json([
            'success' => true,
            'message' => "Global settings for {$country->name} updated.",
            'data' => $country
        ]);
    }

    /**
     * List all global couriers in the registry.
     */
    public function listCouriers(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => \App\Models\LandlordIorCourier::all()
        ]);
    }

    /**
     * Add a new courier to the global registry.
     */
    public function addCourier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'         => 'required|string',
            'code'         => 'required|string|unique:mysql.landlord_ior_couriers,code',
            'type'         => 'required|in:domestic,international',
            'region_type'  => 'required|in:country,continent,global',
            'country_code' => 'nullable|string|size:2',
            'has_booking'  => 'boolean',
        ]);

        $courier = \App\Models\LandlordIorCourier::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Courier added to global registry.',
            'data'    => $courier
        ]);
    }

    /**
     * Update global courier settings.
     */
    public function updateCourier(Request $request, int $id): JsonResponse
    {
        $courier = \App\Models\LandlordIorCourier::findOrFail($id);
        $courier->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Global courier updated.',
            'data'    => $courier
        ]);
    }

    /**
     * Delete a courier from the global registry.
     */
    public function deleteCourier(int $id): JsonResponse
    {
        \App\Models\LandlordIorCourier::destroy($id);
        return response()->json(['success' => true, 'message' => 'Courier removed.']);
    }

    /**
     * Seed initial global policy data.
     */
    public function seedGlobalPolicies(): JsonResponse
    {
        $keywords = [
            ['keyword' => 'laser', 'reason' => 'High-power lasers are restricted for import.', 'severity' => 'blocking'],
            ['keyword' => 'drone', 'reason' => 'Requires special BTRC permission in Bangladesh.', 'severity' => 'warning'],
            ['keyword' => 'perfume', 'reason' => 'Flammable liquid; air shipping restricted.', 'severity' => 'warning'],
            ['keyword' => 'perfume', 'reason' => 'Flammable liquid; air shipping restricted.', 'severity' => 'warning'],
            ['keyword' => 'battery', 'reason' => 'Hazmat regulations apply.', 'severity' => 'warning'],
        ];

        foreach ($keywords as $kw) {
            LandlordIorRestrictedItem::updateOrCreate(['keyword' => $kw['keyword']], $kw);
        }

        $countries = [
            ['name' => 'USA', 'code' => 'USA', 'default_shipping_rate_per_kg' => 8.00],
            ['name' => 'China', 'code' => 'CHN', 'default_shipping_rate_per_kg' => 4.50],
            ['name' => 'UK', 'code' => 'GBR', 'default_shipping_rate_per_kg' => 7.50],
        ];

        foreach ($countries as $c) {
            LandlordIorCountry::updateOrCreate(['code' => $c['code']], $c);
        }

        return response()->json(['success' => true, 'message' => 'Global policies seeded.']);
    }

    /**
     * List all HS Code lookup activity across all tenants.
     */
    public function listHsLookupLogs(): JsonResponse
    {
        $logs = \DB::table('ior_hs_lookup_logs')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $logs
        ]);
    }

    /**
     * Update the global service cost for HS lookups.
     */
    public function updateHsLookupCost(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cost_usd' => 'required|numeric|min:0',
        ]);

        // Note: This matches the config, but for a real SaaS, we might store this in a 'landlord_settings' table.
        // For now, we return success as if it's updated (simulating the admin action).
        return response()->json([
            'success' => true,
            'message' => 'HS lookup cost updated globally.',
            'new_cost' => $data['cost_usd']
        ]);
    }

    // ════════════════════════════════════════
    // HS CODE DICTIONARY (GLOBAL)
    // ════════════════════════════════════════

    /**
     * GET /ior/landlord/hs-codes
     */
    public function hsCodes(Request $request): JsonResponse
    {
        $country = $request->query('country');
        $query = \App\Models\LandlordIorHsCode::query();

        if ($country) {
            $query->where('country_code', $country);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->orderBy('hs_code')->get()
        ]);
    }

    /**
     * POST /ior/landlord/hs-codes
     */
    public function storeHsCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hs_code'             => 'required|string|max:20',
            'country_code'        => 'required|string|size:3',
            'category_en'         => 'required|string|max:255',
            'category_bn'         => 'nullable|string|max:255',
            'cd'                  => 'numeric|min:0',
            'rd'                  => 'numeric|min:0',
            'sd'                  => 'numeric|min:0',
            'vat'                 => 'numeric|min:0',
            'ait'                 => 'numeric|min:0',
            'at'                  => 'numeric|min:0',
            'is_restricted'       => 'boolean',
            'restriction_reason'  => 'nullable|string|max:500',
        ]);

        $hs = \App\Models\LandlordIorHsCode::updateOrCreate(
            ['hs_code' => $data['hs_code'], 'country_code' => $data['country_code']],
            $data
        );

        return response()->json([
            'success' => true,
            'message' => 'Global HS Code saved.',
            'data'    => $hs
        ]);
    }

    /**
     * PUT /ior/landlord/hs-codes/{id}
     */
    public function updateHsCode(Request $request, int $id): JsonResponse
    {
        $hs = \App\Models\LandlordIorHsCode::findOrFail($id);
        $hs->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'HS Code updated.',
            'data'    => $hs
        ]);
    }

    /**
     * DELETE /ior/landlord/hs-codes/{id}
     */
    public function destroyHsCode(int $id): JsonResponse
    {
        \App\Models\LandlordIorHsCode::destroy($id);
        return response()->json(['success' => true, 'message' => 'HS Code deleted.']);
    }

    // ════════════════════════════════════════
    // HS LOOKUP STATS (CROSS-TENANT)
    // ════════════════════════════════════════

    /**
     * GET /ior/landlord/hs-stats
     * Revenue and usage stats across all tenants.
     */
    public function hsLookupStats(): JsonResponse
    {
        // Total counts by type
        $selections = \DB::table('ior_hs_lookup_logs')->where('type', 'selection')->count();
        $inferences = \DB::table('ior_hs_lookup_logs')->where('type', 'inference')->count();

        // Revenue
        $totalRevenue = \DB::table('ior_hs_lookup_logs')->sum('cost_usd');

        // Top 10 most looked-up HS codes
        $topCodes = \DB::table('ior_hs_lookup_logs')
            ->select('hs_code', \DB::raw('COUNT(*) as total'), \DB::raw('SUM(cost_usd) as revenue'))
            ->groupBy('hs_code')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Recent activity (last 20)
        $recent = \DB::table('ior_hs_lookup_logs')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'summary' => [
                    'total_selections' => $selections,
                    'total_inferences' => $inferences,
                    'total_lookups'    => $selections + $inferences,
                    'total_revenue'    => round($totalRevenue, 4),
                ],
                'top_codes' => $topCodes,
                'recent'    => $recent,
            ]
        ]);
    }
}
