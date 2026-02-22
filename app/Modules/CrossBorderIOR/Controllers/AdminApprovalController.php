<?php

namespace App\Modules\CrossBorderIOR\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CrossBorderIOR\Services\ProductApprovalService;
use App\Modules\CrossBorderIOR\Services\BlockedSourceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminApprovalController extends Controller
{
    public function __construct(
        private ProductApprovalService $approvalService,
        private BlockedSourceService $blockedService
    ) {}

    /**
     * Block a specific SKU (Kill-Switch).
     */
    public function blockSku(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        DB::table('ec_products')->where('id', $id)->update([
            'is_active'    => false,
            'block_reason' => $request->reason,
            'updated_at'   => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'SKU has been blocked and taken offline.'
        ]);
    }

    /**
     * Mark as warehouse verified.
     */
    public function verifyWarehouse(Request $request, int $id): JsonResponse
    {
        $verified = (bool) $request->input('verified', true);
        $this->approvalService->verifyWarehouseStock($id, $verified);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse verification status updated.'
        ]);
    }

    /**
     * List products pending rewrite/approval.
     */
    public function pendingList(): JsonResponse
    {
        $products = DB::table('ec_products')
            ->where('product_type', 'foreign')
            ->where('content_status', '!=', 'approved')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $products
        ]);
    }

    /**
     * Trigger AI Rewrite for a product.
     */
    public function rewrite(Request $request, int $id): JsonResponse
    {
        $lang = $request->input('lang', 'both');
        try {
            $result = $this->approvalService->rewriteProduct($id, $lang);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Approve a product.
     */
    public function approve(int $id): JsonResponse
    {
        try {
            $result = $this->approvalService->approve($id);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Block a domain (Kill-Switch).
     */
    public function blockDomain(Request $request): JsonResponse
    {
        $request->validate([
            'domain' => 'required|string|max:255',
            'reason' => 'nullable|string|max:500'
        ]);

        $this->blockedService->blockDomain($request->domain, $request->reason);

        return response()->json([
            'success' => true,
            'message' => "Domain {$request->domain} has been blocked."
        ]);
    }

    /**
     * List blocked domains.
     */
    public function blockedDomains(): JsonResponse
    {
        $blocked = DB::table('ior_blocked_sources')->get();
        return response()->json([
            'success' => true,
            'data'    => $blocked
        ]);
    }
}
