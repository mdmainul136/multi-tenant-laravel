<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ModuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    protected $moduleService;

    public function __construct(ModuleService $moduleService)
    {
        $this->moduleService = $moduleService;
    }

    /**
     * Get all available modules
     */
    public function index()
    {
        try {
            $modules = $this->moduleService->getAvailableModules();

            return response()->json([
                'success' => true,
                'data' => $modules
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching modules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tenant's subscribed modules
     */
    public function tenantModules(Request $request)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id');
            $modules = $this->moduleService->getTenantModules($tenantId);

            return response()->json([
                'success' => true,
                'data' => $modules
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching tenant modules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Subscribe to a module
     */
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'module_key' => 'required|string',
        ], [
            'module_key.required' => 'Module key is required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tenantId = $request->attributes->get('tenant_id');
            $result = $this->moduleService->subscribeModule(
                $tenantId,
                $request->module_key
            );

            if ($result['success']) {
                return response()->json($result, 201);
            } else {
                return response()->json($result, 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error subscribing to module',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unsubscribe from a module
     */
    public function unsubscribe(Request $request, $moduleKey)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id');
            $result = $this->moduleService->unsubscribeModule($tenantId, $moduleKey);

            if ($result['success']) {
                return response()->json($result);
            } else {
                return response()->json($result, 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error unsubscribing from module',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if tenant has access to a module
     */
    public function checkAccess(Request $request, $moduleKey)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id');
            $hasAccess = $this->moduleService->isModuleActive($tenantId, $moduleKey);

            return response()->json([
                'success' => true,
                'has_access' => $hasAccess
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking module access',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
