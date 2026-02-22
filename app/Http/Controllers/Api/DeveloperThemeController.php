<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Theme;
use Illuminate\Support\Facades\Validator;

class DeveloperThemeController extends Controller
{
    /**
     * List themes submitted by the authenticated developer.
     */
    public function index(Request $request)
    {
        $developerId = $request->user()->id; // Assuming user is the developer
        $themes = Theme::where('developer_id', $developerId)->get();
        return response()->json($themes);
    }

    /**
     * Submit a new theme for review.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'vertical' => 'required|string|max:255',
            'component_blueprint' => 'required|array',
            'capabilities' => 'array',
            'preview_url' => 'required|string',
            'price' => 'numeric|min:0',
            'version' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Soho-Grade Schema Validation
        $blueprint = $request->component_blueprint;
        if (!isset($blueprint['globalSettings']) || !isset($blueprint['layouts']['home'])) {
            return response()->json([
                'message' => "Invalid Theme Blueprint: Missing 'globalSettings' or 'home' layout."
            ], 422);
        }

        $theme = Theme::create([
            'developer_id' => $request->user()->id,
            'name' => $request->name,
            'vertical' => $request->vertical,
            'component_blueprint' => $blueprint,
            'config' => $blueprint['globalSettings'], // Base style config
            'capabilities' => $request->capabilities ?? [],
            'preview_url' => $request->preview_url,
            'price' => $request->price ?? 0.00,
            'is_premium' => ($request->price > 0),
            'version' => $request->version ?? '1.0.0',
            'submission_status' => 'pending',
            'is_active' => false,
        ]);

        return response()->json([
            'message' => 'Theme submitted successfully and is pending review.',
            'theme' => $theme
        ], 201);
    }

    /**
     * Update an existing theme (if not already active/locked).
     */
    public function update(Request $request, $id)
    {
        $theme = Theme::where('developer_id', $request->user()->id)->findOrFail($id);

        if ($theme->submission_status === 'active') {
            return response()->json(['message' => 'Active themes cannot be edited. Please submit a new version.'], 403);
        }

        $theme->update($request->all());

        return response()->json([
            'message' => 'Theme updated successfully.',
            'theme' => $theme
        ]);
    }

    /**
     * Delete a theme.
     */
    public function destroy(Request $request, $id)
    {
        $theme = Theme::where('developer_id', $request->user()->id)->findOrFail($id);
        $theme->delete();

        return response()->json(['message' => 'Theme deleted successfully.']);
    }
}
