<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Theme;
use App\Models\Tenant;

class ThemeController extends Controller
{
    /**
     * List all available themes in the gallery.
     */
    public function index()
    {
        $themes = Theme::where('is_active', true)->get();
        return response()->json($themes);
    }

    /**
     * Get details of a single theme.
     */
    public function show($id)
    {
        $theme = Theme::findOrFail($id);
        return response()->json($theme);
    }

    /**
     * Adopt a theme for the current tenant.
     * This merges the theme's config into the tenant's existing setup.
     */
    public function adopt(Request $request, $id)
    {
        $tenantId = $request->header('X-Tenant-Id');
        $theme = Theme::findOrFail($id);
        $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();

        // Map theme config to tenant properties
        $config = $theme->config;
        
        $tenant->update([
            'primary_color' => $config['primaryColor'] ?? $tenant->primary_color,
            'secondary_color' => $config['accentColor'] ?? $tenant->secondary_color,
            // Add more mappings as needed (fonts, hero text etc would go to a specialized tenant_settings table usually)
        ]);

        return response()->json([
            'message' => "Theme '{$theme->name}' adopted successfully.",
            'applied_config' => $config
        ]);
    }

    /**
     * CRUD: Store a new theme (Admin Only logic usually handled by middleware)
     */
    public function store(Request $request)
    {
        $theme = Theme::create($request->all());
        return response()->json([
            'message' => 'New theme template created.',
            'theme' => $theme
        ], 201);
    }

    /**
     * CRUD: Update a theme template
     */
    public function update(Request $request, $id)
    {
        $theme = Theme::findOrFail($id);
        $theme->update($request->all());
        return response()->json([
            'message' => 'Theme template updated.',
            'theme' => $theme
        ]);
    }
}
