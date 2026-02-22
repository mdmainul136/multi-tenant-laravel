<?php

namespace App\Modules\Tracking\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tracking\TrackingContainer;
use App\Models\Tracking\TrackingDestination;
use App\Modules\Tracking\Services\MetaCapiService;
use App\Modules\Tracking\Services\SignalsGatewayService;
use App\Modules\Tracking\Services\EventValidationService;
use App\Modules\Tracking\Services\DataFilterService;
use Illuminate\Http\Request;

/**
 * Signals Gateway Controller.
 *
 * Provides:
 *   - Direct event sending via Signals Gateway pipeline
 *   - Pipeline configuration (CRUD)
 *   - Event validation against CAPI schema
 *   - Event Match Quality (EMQ) scoring
 */
class SignalsController extends Controller
{
    public function __construct(
        private MetaCapiService $metaCapi,
        private SignalsGatewayService $signalsGateway,
        private EventValidationService $validator,
        private DataFilterService $dataFilter,
    ) {}

    /**
     * Send event(s) through the Signals Gateway pipeline.
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'container_id' => 'required|integer',
            'events'       => 'required|array|min:1',
            'events.*.event_name' => 'required|string',
        ]);

        $container = TrackingContainer::findOrFail($validated['container_id']);
        $events = $validated['events'];

        // Gather all destination credentials
        $destinations = TrackingDestination::where('container_id', $container->id)
            ->where('is_active', true)
            ->where('is_gateway', false)
            ->get();

        $credentials = [];
        foreach ($destinations as $dest) {
            $credentials[$dest->type] = $dest->credentials;
        }

        // Apply data filters
        $filterConfig = $container->settings['data_filters'] ?? [];
        $filteredEvents = [];
        foreach ($events as $event) {
            if (!empty($filterConfig)) {
                $filtered = $this->dataFilter->applyFilters($event, $filterConfig);
                if ($filtered !== null) {
                    $filteredEvents[] = $filtered;
                }
            } else {
                $filteredEvents[] = $event;
            }
        }

        if (empty($filteredEvents)) {
            return response()->json([
                'success'  => true,
                'message'  => 'All events were filtered out by data collection rules',
                'sent'     => 0,
            ]);
        }

        // Process through pipeline
        $pipelineConfig = $container->settings['pipeline_config'] ?? [];
        $results = [];

        foreach ($filteredEvents as $event) {
            $results[] = $this->signalsGateway->processEvent($event, $pipelineConfig, $credentials);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'events_received' => count($events),
                'events_filtered' => count($events) - count($filteredEvents),
                'events_processed' => count($filteredEvents),
                'results' => $results,
            ],
        ]);
    }

    /**
     * Get pipeline configuration for a container.
     */
    public function getPipelines(int $id)
    {
        $container = TrackingContainer::findOrFail($id);
        $pipelineConfig = $container->settings['pipeline_config'] ?? ['pipelines' => []];

        return response()->json(['success' => true, 'data' => $pipelineConfig]);
    }

    /**
     * Update pipeline configuration.
     */
    public function updatePipelines(Request $request, int $id)
    {
        $container = TrackingContainer::findOrFail($id);

        $validated = $request->validate([
            'pipelines'                     => 'required|array',
            'pipelines.*.name'              => 'required|string',
            'pipelines.*.enabled'           => 'boolean',
            'pipelines.*.filters'           => 'array',
            'pipelines.*.destinations'      => 'required|array',
            'pipelines.*.transforms'        => 'array',
        ]);

        $settings = $container->settings ?? [];
        $settings['pipeline_config'] = $validated;
        $container->update(['settings' => $settings]);

        return response()->json([
            'success'  => true,
            'data'     => $validated,
            'message'  => 'Pipeline configuration updated',
        ]);
    }

    /**
     * Validate event(s) against Meta CAPI schema.
     */
    public function validateEvent(Request $request)
    {
        $validated = $request->validate([
            'events'   => 'required|array|min:1',
        ]);

        $result = $this->validator->validateBatch($validated['events']);

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Get Event Match Quality score for a sample event.
     */
    public function getEMQ(int $id, Request $request)
    {
        $container = TrackingContainer::findOrFail($id);

        // Use sample user_data from request or from latest event log
        $sampleUserData = $request->input('user_data', []);

        $emq = $this->metaCapi->calculateEMQ($sampleUserData);

        return response()->json(['success' => true, 'data' => $emq]);
    }
}
