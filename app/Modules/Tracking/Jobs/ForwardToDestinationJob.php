<?php

namespace App\Modules\Tracking\Jobs;

use App\Models\Tracking\TrackingDestination;
use App\Modules\Tracking\Services\DestinationService;
use App\Modules\Tracking\Services\FieldMappingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ForwardToDestinationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $destinationId;
    protected $payload;
    protected $containerMappings;
    protected $powerUps;

    /**
     * Create a new job instance.
     */
    public function __construct(int $destinationId, array $payload, ?array $containerMappings = null, ?array $powerUps = null)
    {
        $this->destinationId = $destinationId;
        $this->payload = $payload;
        $this->containerMappings = $containerMappings;
        $this->powerUps = $powerUps ?? [];

        // Apply request delay if the destination has one configured
        $destination = TrackingDestination::find($destinationId);
        if ($destination && ($destination->delay_minutes ?? 0) > 0) {
            $this->delay(now()->addMinutes($destination->delay_minutes));
        }
    }

    /**
     * Execute the job.
     */
    public function handle(DestinationService $service, FieldMappingService $mappingService): void
    {
        $destination = TrackingDestination::find($this->destinationId);
        
        if (!$destination || !$destination->is_active) {
            return;
        }

        // Apply custom field mappings from both destination-specific and container-wide settings
        $mergedMappings = array_merge($destination->mappings ?? [], $this->containerMappings ?? []);
        $finalPayload = $mappingService->applyMappings($this->payload, $mergedMappings);

        // Power-Up: Phone E.164 Formatter
        if (in_array('phone_formatter', $this->powerUps)) {
            $finalPayload = $this->formatPhoneNumbers($finalPayload);
        }

        // Power-Up: POAS (Profit on Ad Spend) Calculator
        if (in_array('poas', $this->powerUps)) {
            $finalPayload = $this->calculatePOAS($finalPayload);
        }

        try {
            match ($destination->type) {
                'facebook_capi' => $service->sendToFacebookCapi($finalPayload, $destination->credentials),
                'ga4'           => $service->sendToGA4($finalPayload, $destination->credentials),
                'tiktok'        => $service->sendToTikTok($finalPayload, $destination->credentials),
                'snapchat'      => $service->sendToSnapchat($finalPayload, $destination->credentials),
                'twitter'       => $service->sendToTwitter($finalPayload, $destination->credentials),
                'webhook'       => $service->sendToWebhook($finalPayload, $destination->credentials),
                default         => Log::warning("Unknown destination type: {$destination->type}"),
            };
        } catch (\Exception $e) {
            Log::error("Failed to forward tracking event to {$destination->type}: " . $e->getMessage());
            throw $e; // Retry if failed
        }
    }

    /**
     * Format phone numbers to E.164 international standard.
     * Handles common formats: +1234567890, (123) 456-7890, 123-456-7890, etc.
     */
    private function formatPhoneNumbers(array $payload): array
    {
        $phoneFields = ['user_data.ph', 'user_data.phone', 'phone', 'phone_number'];

        foreach ($phoneFields as $field) {
            $value = data_get($payload, $field);
            if ($value && is_string($value)) {
                // Strip all non-numeric except leading +
                $cleaned = preg_replace('/[^\d+]/', '', $value);
                
                // Ensure it starts with + (assume US if no country code)
                if (!str_starts_with($cleaned, '+')) {
                    // If 10 digits, assume US (+1)
                    if (strlen($cleaned) === 10) {
                        $cleaned = '+1' . $cleaned;
                    } elseif (strlen($cleaned) === 11 && str_starts_with($cleaned, '1')) {
                        $cleaned = '+' . $cleaned;
                    } else {
                        $cleaned = '+' . $cleaned;
                    }
                }

                data_set($payload, $field, $cleaned);
            }
        }

        return $payload;
    }

    /**
     * Calculate Profit on Ad Spend (POAS).
     * Adjusts conversion value to reflect profit rather than revenue.
     *
     * If custom_data contains: value=100, cost_of_goods=40
     * POAS value becomes: 60 (profit = revenue - COGS)
     */
    private function calculatePOAS(array $payload): array
    {
        $revenue = $payload['custom_data']['value'] ?? null;
        $cogs = $payload['custom_data']['cost_of_goods'] ?? null;

        if ($revenue !== null && $cogs !== null) {
            $profit = max(0, (float) $revenue - (float) $cogs);
            $payload['custom_data']['original_value'] = $revenue;
            $payload['custom_data']['value'] = round($profit, 2);
            $payload['custom_data']['poas_adjusted'] = true;
        }

        return $payload;
    }
}

