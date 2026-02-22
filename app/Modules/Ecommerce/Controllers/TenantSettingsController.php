<?php

namespace App\Modules\Ecommerce\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantSettingsController extends Controller
{
    /**
     * Get storefront settings and branding for the identified tenant.
     */
    public function show(Request $request)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id');
            
            // The master database contains the tenant branding info
            $tenant = Tenant::where('tenant_id', $tenantId)->first();

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant settings not found'
                ], 404);
            }

            // In a real app, you might have a dedicated settings table.
            // For now, we return branding from the tenant model.
            return response()->json([
                'success' => true,
                'data' => [
                    'tenant_id' => $tenant->tenant_id,
                    'name' => $tenant->tenant_name,
                    'brand_name_primary' => $tenant->tenant_name,
                    'brand_name_accent' => ' Store',
                    'logo_image_url' => $tenant->logo_url ?? null,
                    'logo_bg_color' => $tenant->primary_color ?? '#3b82f6',
                    'logo_text_color' => '#ffffff',
                    'logo_letter' => strtoupper(substr($tenant->tenant_id, 0, 3)),
                    'primary_color' => $tenant->primary_color ?? '#3b82f6',
                    'contact_email' => $tenant->email,
                    'contact_phone' => $tenant->phone,
                    'social_links' => [
                        'facebook' => $tenant->facebook_url ?? null,
                        'instagram' => $tenant->instagram_url ?? null,
                    ],
                    'features' => [
                        'cross_border' => true, // Example flag
                        'reviews_enabled' => true,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching tenant settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
