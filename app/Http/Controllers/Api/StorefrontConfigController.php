<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TenantStorefrontConfig;
use App\Models\Theme;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StorefrontConfigController extends Controller
{
    /**
     * Save a new draft of the storefront configuration.
     */
    public function saveDraft(Request $request)
    {
        $tenantId = $request->header('X-Tenant-Id');
        
        $request->validate([
            'theme_id' => 'nullable|exists:themes,id',
            'config' => 'required|array',
        ]);

        // Find or create the current draft
        $draft = TenantStorefrontConfig::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'status' => 'draft'
            ],
            [
                'theme_id' => $request->theme_id,
                'config_json' => $request->config,
                'updated_at' => Carbon::now()
            ]
        );

        return response()->json([
            'message' => 'Draft saved successfully.',
            'draft' => $draft
        ]);
    }

    /**
     * Publish the current draft configuration.
     */
    public function publish(Request $request)
    {
        $tenantId = $request->header('X-Tenant-Id');

        $draft = TenantStorefrontConfig::where('tenant_id', $tenantId)
            ->where('status', 'draft')
            ->firstOrFail();

        DB::transaction(function () use ($draft, $tenantId) {
            // Archive existing published config
            TenantStorefrontConfig::where('tenant_id', $tenantId)
                ->where('status', 'published')
                ->update(['status' => 'archived']);

            // Create new published record (to keep history)
            $published = TenantStorefrontConfig::create([
                'tenant_id' => $tenantId,
                'theme_id' => $draft->theme_id,
                'config_json' => $draft->config_json,
                'status' => 'published',
                'published_at' => Carbon::now(),
                'version' => Carbon::now()->format('YmdHis'),
            ]);
        });

        return response()->json([
            'message' => 'Storefront published successfully and is now live.'
        ]);
    }

    /**
     * List version history for rollback.
     */
    public function history(Request $request)
    {
        $tenantId = $request->header('X-Tenant-Id');
        $history = TenantStorefrontConfig::where('tenant_id', $tenantId)
            ->whereIn('status', ['published', 'archived'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json($history);
    }

    /**
     * Rollback to a specific historical version.
     */
    public function rollback(Request $request, $id)
    {
        $tenantId = $request->header('X-Tenant-Id');
        $version = TenantStorefrontConfig::where('tenant_id', $tenantId)->findOrFail($id);

        // Make this version the new draft
        $draft = TenantStorefrontConfig::updateOrCreate(
            ['tenant_id' => $tenantId, 'status' => 'draft'],
            [
                'theme_id' => $version->theme_id,
                'config_json' => $version->config_json,
                'rollback_from' => $version->id,
                'updated_at' => Carbon::now()
            ]
        );

        return response()->json([
            'message' => "Restored version {$version->version} as a draft.",
            'draft' => $draft
        ]);
    }
}
