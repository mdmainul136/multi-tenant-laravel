<?php

namespace App\Modules\Tracking\Services;

use Illuminate\Support\Facades\Log;

/**
 * Signals Gateway Service.
 *
 * Multi-destination data pipeline engine inspired by Meta's Signals Gateway.
 * Provides: Source → Filter → Transform → Route → Destinations
 *
 * Pipeline Configuration (stored as JSON in container settings):
 * {
 *   "pipelines": [
 *     {
 *       "name": "Purchase to Meta + GA4",
 *       "enabled": true,
 *       "filters": [
 *         {"field": "event_name", "operator": "equals", "value": "Purchase"}
 *       ],
 *       "destinations": ["facebook_capi", "ga4"],
 *       "transforms": [
 *         {"type": "rename_field", "from": "total", "to": "value"}
 *       ]
 *     }
 *   ]
 * }
 */
class SignalsGatewayService
{
    public function __construct(
        private DestinationService $destinations,
        private MetaCapiService $metaCapi,
        private DataFilterService $dataFilter,
    ) {}

    /**
     * Process an event through configured pipelines.
     * Returns array of matched pipelines and their forwarding results.
     */
    public function processEvent(array $event, array $pipelineConfig, array $credentials): array
    {
        $pipelines = $pipelineConfig['pipelines'] ?? [];
        $results = [];

        if (empty($pipelines)) {
            // No pipelines configured — use default fanout to all destinations
            return $this->defaultFanout($event, $credentials);
        }

        foreach ($pipelines as $pipeline) {
            if (!($pipeline['enabled'] ?? true)) {
                continue;
            }

            // Step 1: Check if event matches pipeline filters
            if (!$this->matchesFilters($event, $pipeline['filters'] ?? [])) {
                continue;
            }

            // Step 2: Apply transforms
            $transformedEvent = $this->applyTransforms($event, $pipeline['transforms'] ?? []);

            // Step 3: Route to configured destinations
            $pipelineResults = [];
            foreach ($pipeline['destinations'] ?? [] as $destType) {
                try {
                    $creds = $credentials[$destType] ?? [];
                    $result = $this->forwardToDestination($transformedEvent, $destType, $creds);
                    $pipelineResults[$destType] = ['success' => true, 'result' => $result];
                } catch (\Exception $e) {
                    Log::error("[Signals Gateway] Forward failed", [
                        'pipeline'    => $pipeline['name'] ?? 'unnamed',
                        'destination' => $destType,
                        'error'       => $e->getMessage(),
                    ]);
                    $pipelineResults[$destType] = ['success' => false, 'error' => $e->getMessage()];
                }
            }

            $results[] = [
                'pipeline'     => $pipeline['name'] ?? 'unnamed',
                'matched'      => true,
                'destinations' => $pipelineResults,
            ];
        }

        return [
            'pipelines_matched' => count($results),
            'results'           => $results,
        ];
    }

    /**
     * Check if an event matches a set of filters.
     *
     * Supported operators:
     *   equals, not_equals, contains, not_contains,
     *   starts_with, ends_with, regex, exists, not_exists,
     *   in (array of values), greater_than, less_than
     */
    private function matchesFilters(array $event, array $filters): bool
    {
        if (empty($filters)) return true;

        foreach ($filters as $filter) {
            $field    = $filter['field'] ?? '';
            $operator = $filter['operator'] ?? 'equals';
            $value    = $filter['value'] ?? null;

            // Support dot notation for nested fields
            $eventValue = data_get($event, $field);

            $matches = match ($operator) {
                'equals'       => $eventValue == $value,
                'not_equals'   => $eventValue != $value,
                'contains'     => is_string($eventValue) && str_contains($eventValue, $value),
                'not_contains' => is_string($eventValue) && !str_contains($eventValue, $value),
                'starts_with'  => is_string($eventValue) && str_starts_with($eventValue, $value),
                'ends_with'    => is_string($eventValue) && str_ends_with($eventValue, $value),
                'regex'        => is_string($eventValue) && preg_match($value, $eventValue),
                'exists'       => $eventValue !== null,
                'not_exists'   => $eventValue === null,
                'in'           => is_array($value) && in_array($eventValue, $value),
                'greater_than' => is_numeric($eventValue) && $eventValue > $value,
                'less_than'    => is_numeric($eventValue) && $eventValue < $value,
                default        => true,
            };

            // All filters must match (AND logic)
            if (!$matches) return false;
        }

        return true;
    }

    /**
     * Apply transforms to an event.
     *
     * Supported transform types:
     *   rename_field  — Rename a field
     *   set_field     — Set a new field value
     *   remove_field  — Remove a field
     *   copy_field    — Copy value from one field to another
     *   lowercase     — Lowercase a field
     *   uppercase     — Uppercase a field
     *   default_value — Set a default if field is missing
     */
    private function applyTransforms(array $event, array $transforms): array
    {
        foreach ($transforms as $transform) {
            $type = $transform['type'] ?? '';

            switch ($type) {
                case 'rename_field':
                    $from = $transform['from'] ?? '';
                    $to = $transform['to'] ?? '';
                    if ($from && $to && data_get($event, $from) !== null) {
                        data_set($event, $to, data_get($event, $from));
                        data_forget($event, $from);
                    }
                    break;

                case 'set_field':
                    $field = $transform['field'] ?? '';
                    $value = $transform['value'] ?? null;
                    if ($field) {
                        data_set($event, $field, $value);
                    }
                    break;

                case 'remove_field':
                    $field = $transform['field'] ?? '';
                    if ($field) {
                        data_forget($event, $field);
                    }
                    break;

                case 'copy_field':
                    $from = $transform['from'] ?? '';
                    $to = $transform['to'] ?? '';
                    if ($from && $to) {
                        data_set($event, $to, data_get($event, $from));
                    }
                    break;

                case 'lowercase':
                    $field = $transform['field'] ?? '';
                    $val = data_get($event, $field);
                    if (is_string($val)) {
                        data_set($event, $field, strtolower($val));
                    }
                    break;

                case 'uppercase':
                    $field = $transform['field'] ?? '';
                    $val = data_get($event, $field);
                    if (is_string($val)) {
                        data_set($event, $field, strtoupper($val));
                    }
                    break;

                case 'default_value':
                    $field = $transform['field'] ?? '';
                    $value = $transform['value'] ?? null;
                    if ($field && data_get($event, $field) === null) {
                        data_set($event, $field, $value);
                    }
                    break;
            }
        }

        return $event;
    }

    /**
     * Forward event to a specific destination type.
     */
    private function forwardToDestination(array $event, string $type, array $creds): mixed
    {
        return match ($type) {
            'facebook_capi' => $this->metaCapi->sendEvent($event, $creds),
            'ga4'           => $this->destinations->sendToGA4($event, $creds),
            'tiktok'        => $this->destinations->sendToTikTok($event, $creds),
            'snapchat'      => $this->destinations->sendToSnapchat($event, $creds),
            'twitter'       => $this->destinations->sendToTwitter($event, $creds),
            'webhook'       => $this->destinations->sendToWebhook($event, $creds),
            default         => throw new \InvalidArgumentException("Unknown destination: {$type}"),
        };
    }

    /**
     * Default fanout: send event to all available destinations in credentials.
     */
    private function defaultFanout(array $event, array $credentials): array
    {
        $results = [];
        foreach ($credentials as $type => $creds) {
            if (empty($creds)) continue;
            try {
                $result = $this->forwardToDestination($event, $type, $creds);
                $results[$type] = ['success' => true, 'result' => $result];
            } catch (\Exception $e) {
                $results[$type] = ['success' => false, 'error' => $e->getMessage()];
            }
        }
        return ['pipelines_matched' => 0, 'default_fanout' => true, 'results' => $results];
    }
}
