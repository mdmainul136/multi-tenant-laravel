<?php

namespace App\Modules\Tracking\Actions;

use App\Models\Tracking\TrackingContainer;
use App\Models\Tracking\TrackingDestination;
use App\Modules\Tracking\DTOs\TrackingEventDTO;
use App\Modules\Tracking\Jobs\ProcessTrackingEventJob;
use App\Modules\Tracking\Jobs\ForwardToDestinationJob;
use App\Modules\Tracking\Services\PowerUpService;
use App\Modules\Tracking\Services\TrackingUsageService;
use App\Modules\Tracking\Services\DataFilterService;
use App\Modules\Tracking\Services\SignalsGatewayService;

class ProcessTrackingEventAction
{
    public function __construct(
        private PowerUpService $powerUps,
        private TrackingUsageService $usage,
        private DataFilterService $dataFilter,
        private SignalsGatewayService $signalsGateway,
    ) {}

    public function execute(TrackingContainer $container, TrackingEventDTO $dto)
    {
        // Detect tier (In a real app, this would come from tenant's subscription/module_type)
        $tier = 'free'; 

        // Check Monthly Quota
        if ($this->usage->hasReachedLimit($container->id, $tier)) {
            $this->usage->recordEvent($container->id, 'dropped');
            return ['status' => 'error', 'reason' => 'quota_exceeded'];
        }

        // Record event received
        $this->usage->recordEvent($container->id, 'received');

        // Power-Up: Consent Check
        if ($this->powerUps->isEnabled($container, 'consent_mode') && $dto->consent === false) {
            $this->usage->recordEvent($container->id, 'dropped');
            return ['status' => 'dropped', 'reason' => 'no_consent'];
        }

        // Power-Up: Deduplication
        if ($this->powerUps->isEnabled($container, 'dedupe')) {
            if ($dto->eventId && cache()->has("tracking_event_{$dto->eventId}")) {
                $this->usage->recordEvent($container->id, 'dropped');
                return ['status' => 'duplicate', 'event_id' => $dto->eventId];
            }
        }

        // Power-Up: Enrich Data (Geo-IP + PII Hash)
        $enrichedData = $dto->payload;
        
        if ($this->powerUps->isEnabled($container, 'geo_enrich')) {
            $this->usage->recordEvent($container->id, 'power_up');
            $enrichedData['geo'] = [
                'country' => request()->header('CF-IPCountry') ?? 'Unknown',
                'city'    => request()->header('CF-IPCity') ?? 'Unknown',
            ];
        }

        if ($this->powerUps->isEnabled($container, 'pii_hash')) {
            $this->usage->recordEvent($container->id, 'power_up');
            if (isset($enrichedData['user_data'])) {
                $fieldsToHash = ['email' => 'em', 'phone' => 'ph', 'external_id' => 'external_id'];
                foreach ($fieldsToHash as $field => $key) {
                    if (isset($enrichedData['user_data'][$field])) {
                        $enrichedData['user_data'][$key] = hash('sha256', strtolower(trim($enrichedData['user_data'][$field])));
                        if ($key !== $field) unset($enrichedData['user_data'][$field]);
                    }
                }
            }
        }

        // ── Data Filters ───────────────────────────────────
        $filterConfig = $container->settings['data_filters'] ?? [];
        if (!empty($filterConfig)) {
            $enrichedData = $this->dataFilter->applyFilters($enrichedData, $filterConfig);
            if ($enrichedData === null) {
                $this->usage->recordEvent($container->id, 'dropped');
                return ['status' => 'dropped', 'reason' => 'filtered'];
            }
        }

        // Log event asynchronously
        if ($container->settings['data_filters']['store_events'] ?? true) {
            ProcessTrackingEventJob::dispatch([
                'container_id' => $container->id,
                'event_type'   => $dto->eventName,
                'source_ip'    => $dto->sourceIp,
                'user_agent'   => $dto->userAgent,
                'payload'      => $enrichedData,
            ]);
        }

        // ── Signals Gateway Pipeline (if configured) ───────
        $pipelineConfig = $container->settings['pipeline_config'] ?? [];
        if (!empty($pipelineConfig['pipelines'])) {
            // Gather all destination credentials
            $destinations = TrackingDestination::where('container_id', $container->id)
                ->where('is_active', true)
                ->where('is_gateway', false)
                ->get();

            $credentials = [];
            foreach ($destinations as $dest) {
                $credentials[$dest->type] = $dest->credentials;
            }

            // Consent filtering on destinations
            if (!empty($filterConfig['require_consent'])) {
                $allowedTypes = $this->dataFilter->filterDestinationsByConsent(
                    $enrichedData,
                    $filterConfig,
                    array_keys($credentials)
                );
                $credentials = array_intersect_key($credentials, array_flip($allowedTypes));
            }

            $result = $this->signalsGateway->processEvent($enrichedData, $pipelineConfig, $credentials);

            $forwardedCount = 0;
            foreach (($result['results'] ?? []) as $pipelineResult) {
                $dests = is_array($pipelineResult) ? ($pipelineResult['destinations'] ?? $pipelineResult) : [];
                foreach ($dests as $destResult) {
                    if ($destResult['success'] ?? false) $forwardedCount++;
                }
            }

            for ($i = 0; $i < $forwardedCount; $i++) {
                $this->usage->recordEvent($container->id, 'forwarded');
            }
        } else {
            // ── Standard Forwarding (no pipeline) ──────────────
            $destinations = TrackingDestination::where('container_id', $container->id)
                ->where('is_active', true)
                ->where('is_gateway', false)
                ->get();

            $enabledPowerUps = array_keys(array_filter($container->power_ups ?? []));

            foreach ($destinations as $destination) {
                $destMappings = $container->event_mappings[$destination->type] ?? null;
                ForwardToDestinationJob::dispatch(
                    $destination->id,
                    $enrichedData,
                    $destMappings,
                    $enabledPowerUps
                );
                $this->usage->recordEvent($container->id, 'forwarded');
            }
        }

        // Cache for Deduplication
        if ($dto->eventId && $this->powerUps->isEnabled($container, 'dedupe')) {
            cache()->put("tracking_event_{$dto->eventId}", true, now()->addDay());
        }
        
        return $enrichedData;
    }
}

