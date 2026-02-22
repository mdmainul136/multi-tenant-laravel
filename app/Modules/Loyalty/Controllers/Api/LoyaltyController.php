<?php

namespace App\Modules\Loyalty\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Loyalty\Models\LoyaltyProgram;
use App\Modules\Loyalty\Models\LoyaltyPoint;
use App\Modules\Loyalty\Models\LoyaltyTier;
use App\Modules\Loyalty\Services\LoyaltyService;
use Illuminate\Support\Facades\Validator;

class LoyaltyController extends Controller
{
    public function getSettings(Request $request)
    {
        $tenantId = $request->header('X-Tenant-Id');
        $settings = LoyaltyProgram::where('tenant_id', $tenantId)->first();

        if (!$settings) {
            // Return default settings if not configured
            return response()->json([
                'tenant_id' => $tenantId,
                'points_per_currency' => 1.0,
                'currency_per_point' => 0.1,
                'min_redemption_points' => 100,
                'is_active' => false
            ]);
        }

        return response()->json($settings);
    }

    public function updateSettings(Request $request)
    {
        $tenantId = $request->header('X-Tenant-Id');
        
        $validator = Validator::make($request->all(), [
            'points_per_currency' => 'required|numeric|min:0',
            'currency_per_point' => 'required|numeric|min:0',
            'min_redemption_points' => 'required|integer|min:0',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $settings = LoyaltyProgram::updateOrCreate(
            ['tenant_id' => $tenantId],
            $request->all()
        );

        return response()->json([
            'message' => 'Loyalty program updated successfully.',
            'settings' => $settings
        ]);
    }

    public function getCustomerPoints(Request $request, $customerId)
    {
        $tenantId = $request->header('X-Tenant-Id');
        
        $points = LoyaltyPoint::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->sum('points');

        $history = LoyaltyPoint::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'balance' => (int)$points,
            'history' => $history
        ]);
    }

    public function getTiers(Request $request)
    {
        $tenantId = $request->header('X-Tenant-Id');
        $tiers = LoyaltyTier::where('tenant_id', $tenantId)
            ->orderBy('min_points', 'asc')
            ->get();

        return response()->json($tiers);
    }
}
