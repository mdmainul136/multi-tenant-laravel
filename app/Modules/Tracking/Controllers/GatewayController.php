<?php

namespace App\Modules\Tracking\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tracking\TrackingDestination;
use App\Modules\Tracking\Services\DestinationService;
use Illuminate\Http\Request;

/**
 * Dedicated Gateway Controller.
 *
 * Provides lightweight, single-destination endpoints that bypass the full sGTM
 * container stack. Ideal for tenants who only need one platform (e.g. Facebook CAPI).
 *
 * Flow: Client → Gateway Endpoint → Single Destination API
 * (No Docker container, no Power-Ups, just direct forwarding)
 */
class GatewayController extends Controller
{
    public function __construct(
        private DestinationService $destinations
    ) {}

    /**
     * List all configured gateways for this tenant.
     */
    public function index()
    {
        $gateways = TrackingDestination::where('is_gateway', true)->get();

        return response()->json(['success' => true, 'data' => $gateways]);
    }

    /**
     * Create a new dedicated gateway.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'        => 'required|string|in:facebook_capi,ga4,tiktok,snapchat,twitter,webhook',
            'name'        => 'required|string',
            'credentials' => 'required|array',
        ]);

        $gateway = TrackingDestination::create([
            ...$validated,
            'is_gateway'   => true,
            'is_active'    => true,
            'container_id' => 0, // Gateway doesn't belong to a container
        ]);

        return response()->json([
            'success' => true,
            'data'    => $gateway,
            'endpoint' => "/api/tracking/gateway/{$gateway->id}/send",
        ], 201);
    }

    /**
     * Delete a gateway.
     */
    public function destroy(int $id)
    {
        $gateway = TrackingDestination::where('is_gateway', true)->findOrFail($id);
        $gateway->delete();

        return response()->json(['success' => true]);
    }
}
