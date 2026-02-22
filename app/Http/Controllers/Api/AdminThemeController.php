<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Theme;

class AdminThemeController extends Controller
{
    /**
     * List all submitted themes pending review.
     */
    public function index()
    {
        $themes = Theme::where('submission_status', 'pending')
            ->with('developer')
            ->get();
            
        return response()->json($themes);
    }

    /**
     * Create a new platform-level theme (Admin only).
     */
    public function store(Request $request)
    {
        $theme = Theme::create(array_merge($request->all(), [
            'submission_status' => 'active',
            'is_active' => true,
        ]));

        return response()->json([
            'message' => 'New enterprise theme created successfully.',
            'theme' => $theme
        ], 201);
    }

    /**
     * Update an existing theme blueprint.
     */
    public function update(Request $request, $id)
    {
        $theme = Theme::findOrFail($id);
        $theme->update($request->all());

        return response()->json([
            'message' => 'Theme blueprint updated successfully.',
            'theme' => $theme
        ]);
    }

    /**
     * Approve a submitted theme.
     */
    public function approve($id)
    {
        $theme = Theme::findOrFail($id);
        
        $theme->update([
            'submission_status' => 'active',
            'is_active' => true,
        ]);

        return response()->json([
            'message' => "Theme '{$theme->name}' has been approved and is now live in the gallery.",
            'theme' => $theme
        ]);
    }

    /**
     * Reject a submitted theme.
     */
    public function reject(Request $request, $id)
    {
        $theme = Theme::findOrFail($id);
        
        $theme->update([
            'submission_status' => 'rejected',
            'is_active' => false,
        ]);

        return response()->json([
            'message' => "Theme '{$theme->name}' has been rejected.",
            'theme' => $theme
        ]);
    }
    /**
     * Simulate a marketplace revenue capture and payout (Stub).
     */
    public function captureRevenue(Request $request, $id)
    {
        $theme = Theme::findOrFail($id);
        $purchasePrice = $theme->price;
        $platformFee = $purchasePrice * 0.30; // 30% Platform Fee
        $developerPayout = $purchasePrice - $platformFee;

        // Stub logic for splitting payout
        return response()->json([
            'message' => 'Marketplace revenue captured and split calculated.',
            'summary' => [
                'theme_name' => $theme->name,
                'total_sale' => $purchasePrice,
                'platform_share' => $platformFee,
                'developer_share' => $developerPayout,
                'status' => 'processed_via_stub'
            ]
        ]);
    }

    /**
     * Check if a tenant has a valid license for a theme (Stub).
     */
    public function checkLicense(Request $request)
    {
        $tenantId = $request->header('X-Tenant-Id');
        $themeId = $request->input('theme_id');

        // Logic would normally check theme_licenses table
        return response()->json([
            'has_valid_license' => true,
            'message' => 'License verification successful (Stub Mode)',
            'tenant' => $tenantId,
            'theme' => $themeId
        ]);
    }
}
