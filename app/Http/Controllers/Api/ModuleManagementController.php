<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class ModuleManagementController extends Controller
{
    /**
     * Upload and install a new module from ZIP
     */
    public function upload(Request $request)
    {
        $request->validate([
            'module_zip' => 'required|file|mimes:zip|max:51200', // 50MB max
        ]);

        $zipFile = $request->file('module_zip');
        $zip = new ZipArchive;
        
        if ($zip->open($zipFile->path()) !== TRUE) {
            return response()->json([
                'success' => false,
                'message' => 'Could not open ZIP file'
            ], 400);
        }

        // 1. Find module.json to get metadata and determine folder name
        $moduleConfig = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (basename($filename) === 'module.json') {
                $moduleConfig = json_decode($zip->getFromIndex($i), true);
                break;
            }
        }

        if (!$moduleConfig || !isset($moduleConfig['key'])) {
            $zip->close();
            return response()->json([
                'success' => false,
                'message' => 'Invalid module ZIP: module.json not found or missing module key'
            ], 400);
        }

        $moduleKey = $moduleConfig['key'];
        $modulePath = app_path("Modules/" . ucfirst($moduleKey));

        // 2. Extract to app/Modules/{Key}
        if (File::exists($modulePath)) {
            // Backup or Warn? For now, we overwrite
            File::deleteDirectory($modulePath);
        }

        File::makeDirectory($modulePath, 0755, true);
        
        // Extracting while stripping prefix if any (e.g. if the zip has a top level folder)
        // This is simplified; assumes files are structured correctly
        $zip->extractTo($modulePath);
        $zip->close();

        // 3. Register/Update in Master Modules table
        $module = Module::updateOrCreate(
            ['module_key' => $moduleKey],
            [
                'module_name' => $moduleConfig['name'] ?? ucfirst($moduleKey),
                'description' => $moduleConfig['description'] ?? '',
                'version' => $moduleConfig['version'] ?? '1.0.0',
                'price' => $moduleConfig['price'] ?? 0,
                'is_active' => true,
            ]
        );

        Log::info("Module {$moduleKey} uploaded and installed successfully.");

        return response()->json([
            'success' => true,
            'message' => "Module '{$module->module_name}' installed successfully",
            'data' => $module
        ]);
    }

    /**
     * Get all modules (for super admin)
     */
    public function index(Request $request)
    {
        $query = Module::query();

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('module_key', 'like', "%{$search}%")
                  ->orWhere('module_name', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $modules = $query->withCount('tenantModules')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $modules
        ]);
    }

    /**
     * Create new module
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'module_key' => 'required|string|unique:modules,module_key',
            'module_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'version' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $module = Module::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Module created successfully',
            'data' => $module
        ], 201);
    }

    /**
     * Update module
     */
    public function update(Request $request, $id)
    {
        $module = Module::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'module_key' => 'sometimes|string|unique:modules,module_key,' . $id,
            'module_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'version' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $module->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Module updated successfully',
            'data' => $module
        ]);
    }

    /**
     * Delete module
     */
    public function destroy($id)
    {
        $module = Module::findOrFail($id);

        // Check if module has active subscriptions
        $activeSubscriptions = $module->activeSubscriptions()->count();

        if ($activeSubscriptions > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete module with {$activeSubscriptions} active subscriptions"
            ], 400);
        }

        $module->delete();

        return response()->json([
            'success' => true,
            'message' => 'Module deleted successfully'
        ]);
    }
}
