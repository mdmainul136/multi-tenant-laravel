<?php

namespace App\Modules\CrossBorderIOR\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Modules\CrossBorderIOR\Services\CourierBookingService;
use App\Modules\CrossBorderIOR\Services\CourierTrackingService;
use App\Modules\CrossBorderIOR\Services\OrderNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourierController extends Controller
{
    public function __construct(
        private CourierTrackingService  $tracker,
        private CourierBookingService   $booker,
        private OrderNotificationService $notifier,
        private \App\Modules\CrossBorderIOR\Services\GlobalCourierService $globalCouriers,
    ) {}

    /**
     * GET /ior/courier/available?country=BD&type=domestic
     * List available couriers for a specific country from the landlord registry.
     */
    public function availableCouriers(Request $request): JsonResponse
    {
        $request->validate([
            'country' => 'required|string|size:2',
            'type'    => 'sometimes|string|in:domestic,international'
        ]);

        $couriers = $this->globalCouriers->getAvailableCouriers(
            $request->input('country'),
            $request->input('type')
        );

        return response()->json([
            'success' => true,
            'data'    => $couriers
        ]);
    }

    // ══════════════════════════════════════════
    // TRACKING
    // ══════════════════════════════════════════

    /**
     * GET /ior/courier/track?number={tn}&courier={code}
     * Track any shipment by tracking number.
     * courier_code is optional — auto-detected from number format if omitted.
     */
    public function track(Request $request): JsonResponse
    {
        $request->validate([
            'number'  => 'required|string|max:100',
            'courier' => 'nullable|string|in:fedex,dhl,ups,pathao,steadfast,redx',
        ]);

        $result = $this->tracker->track(
            $request->input('number'),
            $request->input('courier')
        );

        return response()->json([
            'success' => $result['success'],
            'data'    => $result,
        ], $result['success'] ? 200 : 422);
    }

    /**
     * GET /ior/courier/track-order/{orderId}
     * Track the shipment attached to an IOR order.
     * Reads tracking_number + courier_code from the order.
     */
    public function trackOrder(int $orderId): JsonResponse
    {
        $order = IorForeignOrder::findOrFail($orderId);

        if (empty($order->tracking_number)) {
            return response()->json([
                'success' => false,
                'message' => 'No tracking number assigned to this order yet.',
            ], 422);
        }

        $result = $this->tracker->track($order->tracking_number, $order->courier_code);

        // Auto-update order to delivered if courier confirms delivery
        if ($result['is_delivered'] && $order->order_status !== IorForeignOrder::STATUS_DELIVERED) {
            $order->update(['order_status' => IorForeignOrder::STATUS_DELIVERED]);
        }

        return response()->json([
            'success'      => $result['success'],
            'order_number' => $order->order_number,
            'data'         => $result,
        ]);
    }

    /**
     * POST /ior/courier/detect
     * Detect courier from tracking number format.
     */
    public function detectCourier(Request $request): JsonResponse
    {
        $request->validate(['number' => 'required|string|max:100']);
        $detected = $this->tracker->detectCourier($request->input('number'));

        return response()->json([
            'success'  => true,
            'courier'  => $detected,
            'detected' => $detected !== 'unknown',
        ]);
    }

    // ══════════════════════════════════════════
    // SHIPPING RATES (real-time, admin only)
    // ══════════════════════════════════════════

    /**
     * GET /ior/courier/rates
     * Returns configured per-kg rates from ior_shipping_settings table.
     */
    public function rates(): JsonResponse
    {
        $rates = \DB::table('ior_shipping_settings')
            ->where('is_active', true)
            ->get();

        return response()->json(['success' => true, 'data' => $rates]);
    }

    // ══════════════════════════════════════════
    // ASSIGN TRACKING TO ORDER (admin)
    // ══════════════════════════════════════════

    /**
     * POST /ior/courier/assign
     * Assign tracking number + courier to an order.
     */
    public function assign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id'       => 'required|integer|exists:ior_foreign_orders,id',
            'tracking_number'=> 'required|string|max:100',
            'courier_code'   => 'required|string|in:fedex,dhl,ups,pathao,steadfast,redx',
        ]);

        $order = IorForeignOrder::findOrFail($data['order_id']);
        $order->update([
            'tracking_number' => $data['tracking_number'],
            'courier_code'    => $data['courier_code'],
            'order_status'    => IorForeignOrder::STATUS_SHIPPED,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Tracking assigned. Order marked as 'shipped'.",
            'data'    => $order->fresh(['transactions']),
        ]);
    }

    // ══════════════════════════════════════════
    // COURIER BOOKING (auto-create parcel)
    // ══════════════════════════════════════════

    /**
     * POST /ior/courier/book
     * Automatically creates a parcel with the chosen courier
     * (Pathao, Steadfast, RedX, FedEx, DHL).
     * On success the order is updated with tracking_number + status=shipped
     * and a shipping email is fired.
     */
    public function book(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id'    => 'required|integer|exists:ior_foreign_orders,id',
            'courier_code'=> 'required|string|in:pathao,steadfast,redx,fedex,dhl',
        ]);

        $order  = IorForeignOrder::findOrFail($data['order_id']);
        $result = $this->booker->book($order, $data['courier_code']);

        // Fire shipping notification when booking succeeds
        if ($result['success']) {
            $order->refresh();
            $this->notifier->sendShipped($order);
        }

        return response()->json([
            'success' => $result['success'],
            'data'    => $result,
        ], $result['success'] ? 200 : 422);
    }
}
