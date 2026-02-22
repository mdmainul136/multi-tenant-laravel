<?php

namespace App\Modules\Tracking\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tracking\TrackingContainer;
use App\Modules\Tracking\Actions\ContainerLifecycleAction;
use App\Modules\Tracking\Services\PowerUpService;
use App\Modules\Tracking\Services\TrackingUsageService;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    // ── Container CRUD ──────────────────────────────────────

    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => TrackingContainer::all()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string',
            'container_id' => 'required|string|unique:tenant_dynamic.ec_tracking_containers,container_id',
            'domain'       => 'nullable|string',
            'power_ups'    => 'nullable|array',
        ]);

        $container = TrackingContainer::create($validated);

        return response()->json(['success' => true, 'data' => $container], 201);
    }

    // ── Stats & Logs ────────────────────────────────────────

    public function stats($id)
    {
        $container = TrackingContainer::findOrFail($id);
        
        $stats = [
            'total_events' => $container->eventLogs()->count(),
            'events_by_type' => $container->eventLogs()
                ->selectRaw('event_type, count(*) as count')
                ->groupBy('event_type')
                ->get(),
            'recent_errors' => $container->eventLogs()
                ->where('status_code', '>=', 400)
                ->latest()
                ->limit(5)
                ->get(),
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }

    public function logs($id)
    {
        $container = TrackingContainer::findOrFail($id);
        $logs = $container->eventLogs()->latest()->paginate(50);

        return response()->json(['success' => true, 'data' => $logs]);
    }

    // ── Destinations ────────────────────────────────────────

    public function getDestinations($id)
    {
        $container = TrackingContainer::findOrFail($id);
        return response()->json(['success' => true, 'data' => $container->destinations]);
    }

    public function addDestination(Request $request, $id)
    {
        $container = TrackingContainer::findOrFail($id);
        
        $validated = $request->validate([
            'type'        => 'required|string|in:facebook_capi,ga4,tiktok,snapchat,twitter,webhook',
            'name'        => 'required|string',
            'credentials' => 'required|array',
            'mappings'    => 'nullable|array',
        ]);

        $destination = \App\Models\Tracking\TrackingDestination::create(array_merge($validated, [
            'container_id' => $container->id
        ]));

        return response()->json(['success' => true, 'data' => $destination], 201);
    }

    // ── Usage Metering (Billing) ────────────────────────────

    public function usage(int $id, Request $request, TrackingUsageService $usageService)
    {
        $container = TrackingContainer::findOrFail($id);

        $usage = $usageService->getUsageForBilling(
            $container->id,
            $request->input('from'),
            $request->input('to')
        );

        return response()->json(['success' => true, 'data' => $usage]);
    }

    public function usageDaily(int $id, TrackingUsageService $usageService)
    {
        $container = TrackingContainer::findOrFail($id);
        $daily = $usageService->getDailyBreakdown($container->id);

        return response()->json(['success' => true, 'data' => $daily]);
    }

    // ── Power-Ups ───────────────────────────────────────────

    public function powerUps()
    {
        return response()->json([
            'success' => true,
            'data' => PowerUpService::registry()
        ]);
    }

    public function updatePowerUps(Request $request, int $id)
    {
        $container = TrackingContainer::findOrFail($id);
        
        $request->validate(['power_ups' => 'required|array']);
        $container->update(['power_ups' => $request->input('power_ups')]);

        return response()->json(['success' => true, 'data' => $container->fresh()]);
    }

    // ── Docker Control Plane (Orchestrator) ────────────────

    public function deploy(Request $request, int $id, \App\Modules\Tracking\Services\DockerOrchestratorService $orchestrator)
    {
        $container = TrackingContainer::findOrFail($id);
        $customDomain = $request->input('domain');

        $result = $orchestrator->deploy($container, $customDomain);

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function provision(Request $request, int $id, ContainerLifecycleAction $action)
    {
        $container = TrackingContainer::findOrFail($id);

        $request->validate([
            'docker_container_id' => 'required|string',
            'docker_port'         => 'required|integer',
        ]);

        $updated = $action->provision(
            $container,
            $request->input('docker_container_id'),
            $request->input('docker_port')
        );

        return response()->json(['success' => true, 'data' => $updated]);
    }

    public function deprovision(int $id, \App\Modules\Tracking\Services\DockerOrchestratorService $orchestrator)
    {
        $container = TrackingContainer::findOrFail($id);
        $result = $orchestrator->stop($container);

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function health(int $id, \App\Modules\Tracking\Services\DockerOrchestratorService $orchestrator)
    {
        $container = TrackingContainer::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $orchestrator->healthCheck($container)
        ]);
    }

    public function updateDomain(Request $request, int $id, \App\Modules\Tracking\Services\DockerOrchestratorService $orchestrator)
    {
        $container = TrackingContainer::findOrFail($id);
        $request->validate(['domain' => 'required|string']);

        $result = $orchestrator->updateDomain($container, $request->input('domain'));

        return response()->json(['success' => true, 'data' => $result]);
    }

    // ── Analytics (Stape Analytics Equivalent) ─────────────

    public function analytics(int $id, Request $request, \App\Modules\Tracking\Services\TrackingAnalyticsService $analyticsService)
    {
        $container = TrackingContainer::findOrFail($id);

        $analytics = $analyticsService->getAnalytics(
            $container->id,
            $request->input('from'),
            $request->input('to')
        );

        return response()->json(['success' => true, 'data' => $analytics]);
    }

    // ── Container Update (Settings, Mappings, etc.) ────────

    public function update(Request $request, int $id)
    {
        $container = TrackingContainer::findOrFail($id);

        $validated = $request->validate([
            'name'           => 'sometimes|string',
            'event_mappings' => 'sometimes|array',
            'settings'       => 'sometimes|array',
            'power_ups'      => 'sometimes|array',
        ]);

        // Merge settings instead of overwriting
        if (isset($validated['settings'])) {
            $validated['settings'] = array_merge($container->settings ?? [], $validated['settings']);
        }

        $container->update($validated);

        return response()->json(['success' => true, 'data' => $container->fresh()]);
    }
}
