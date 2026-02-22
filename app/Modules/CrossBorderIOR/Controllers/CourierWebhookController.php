<?php

namespace App\Modules\CrossBorderIOR\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Modules\CrossBorderIOR\Services\OrderMilestoneService;
use App\Modules\CrossBorderIOR\Services\WebhookVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * CourierWebhookController
 * 
 * Receives real-time status updates from shipping partners.
 */
class CourierWebhookController extends Controller
{
    public function __construct(
        private WebhookVerificationService $verifier,
        private OrderMilestoneService      $milestoneService
    ) {}

    /**
     * POST /ior/webhooks/pathao
     */
    public function pathao(Request $request): JsonResponse
    {
        if (!$this->verifier->verifySource($request, 'pathao')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $payload = $request->input('data', []);
        $consignmentId = $payload['consignment_id'] ?? null;
        $orderStatus   = $payload['order_status']   ?? null;

        if ($consignmentId && $orderStatus) {
            $order = IorForeignOrder::where('tracking_number', $consignmentId)->first();
            if ($order) {
                $this->milestoneService->updateFromCourier($order, $orderStatus, 'pathao');
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * POST /ior/webhooks/steadfast
     */
    public function steadfast(Request $request): JsonResponse
    {
        if (!$this->verifier->verifySource($request, 'steadfast')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $consignmentId = $request->input('consignment_id');
        $status        = $request->input('status');

        if ($consignmentId && $status) {
            $order = IorForeignOrder::where('tracking_number', $consignmentId)->first();
            if ($order) {
                $this->milestoneService->updateFromCourier($order, $status, 'steadfast');
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * POST /ior/webhooks/redx
     */
    public function redx(Request $request): JsonResponse
    {
        if (!$this->verifier->verifySource($request, 'redx')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $trackingId = $request->input('tracking_id');
        $status     = $request->input('parcel_status');

        if ($trackingId && $status) {
            $order = IorForeignOrder::where('tracking_number', $trackingId)->first();
            if ($order) {
                $this->milestoneService->updateFromCourier($order, $status, 'redx');
            }
        }

        return response()->json(['success' => true]);
    }
}
