<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\CrossBorderIOR\IorSetting;

/**
 * CourierTrackingService
 * Supports: FedEx v2, DHL Express, UPS JSON (OAuth 2)
 *
 * Credentials stored in `ior_courier_configs` table (via SettingsController).
 * All tracking attempts degrade gracefully — if a courier is unconfigured,
 * we return status with a helpful message instead of throwing.
 */
class CourierTrackingService
{
    // ──────────────────────────────────────────────────────────────
    /**
     * Main entry: auto-detect courier from tracking number format,
     * or use $courierCode hint.
     */
    public function track(string $trackingNumber, ?string $courierCode = null): array
    {
        $code = $courierCode ?? $this->detectCourier($trackingNumber);

        Log::info("[IOR Courier] Tracking $trackingNumber via $code");

        return match ($code) {
            'fedex'     => $this->trackFedex($trackingNumber),
            'dhl'       => $this->trackDhl($trackingNumber),
            'ups'       => $this->trackUps($trackingNumber),
            'pathao'    => $this->trackPathao($trackingNumber),
            'steadfast' => $this->trackSteadfast($trackingNumber),
            'redx'      => $this->trackRedX($trackingNumber),
            default     => $this->unknownCourier($trackingNumber, $code),
        };
    }

    /**
     * Heuristic courier detection from tracking number format.
     */
    public function detectCourier(string $tn): string
    {
        $tn = strtoupper(trim($tn));
        // FedEx: 12/15/20 digits, or starts with 6
        if (preg_match('/^\d{12}$|^\d{15}$|^\d{20}$/', $tn)) return 'fedex';
        // UPS: starts with 1Z
        if (str_starts_with($tn, '1Z')) return 'ups';
        // DHL: 10-11 digits or JD*** prefix
        if (preg_match('/^\d{10,11}$/', $tn) || str_starts_with($tn, 'JD')) return 'dhl';
        // Steadfast: SF prefix
        if (str_starts_with($tn, 'SF')) return 'steadfast';
        // RedX: RX prefix
        if (str_starts_with($tn, 'RX')) return 'redx';

        return 'unknown';
    }

    // ══════════════════════════════════════════════════════════════
    // FEDEX
    // ══════════════════════════════════════════════════════════════

    private function trackFedex(string $tn): array
    {
        $creds = $this->getCourierCreds('fedex');
        if (empty($creds['api_key']) || empty($creds['api_secret'])) {
            return $this->unconfigured('FedEx', $tn);
        }

        try {
            // Step 1: OAuth2 token
            $token = $this->getFedexToken($creds);

            // Step 2: Track API v1
            $response = Http::withToken($token)
                ->timeout(15)
                ->post('https://apis.fedex.com/track/v1/trackingnumbers', [
                    'includeDetailedScans' => true,
                    'trackingInfo'         => [[
                        'trackingNumberInfo' => ['trackingNumber' => $tn],
                    ]],
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('FedEx track API returned ' . $response->status());
            }

            $data   = $response->json();
            $result = $data['output']['completeTrackResults'][0]['trackResults'][0] ?? null;

            if (!$result) {
                return $this->notFound('FedEx', $tn);
            }

            $latestStatus = $result['latestStatusDetail'] ?? [];
            $events       = collect($result['dateAndTimes'] ?? []);
            $scanEvents   = array_map(fn($e) => [
                'timestamp'   => $e['dateTime'] ?? null,
                'description' => $e['type'] ?? null,
                'location'    => null,
            ], $result['scanEvents'] ?? []);

            return [
                'success'         => true,
                'courier'         => 'FedEx',
                'tracking_number' => $tn,
                'status'          => $latestStatus['description'] ?? 'Unknown',
                'status_code'     => $latestStatus['code'] ?? null,
                'is_delivered'    => ($latestStatus['code'] ?? '') === 'DL',
                'estimated_delivery' => $result['estimatedDeliveryDetails']['estimatedDeliveryWindow']['begins'] ?? null,
                'origin'          => $result['originLocation']['locationContactAndAddress']['address']['city'] ?? null,
                'destination'     => $result['destinationLocation']['locationContactAndAddress']['address']['city'] ?? null,
                'events'          => $scanEvents,
                'raw'             => $result,
            ];
        } catch (\Exception $e) {
            Log::error('[IOR FedEx Track] ' . $e->getMessage());
            return $this->apiError('FedEx', $tn, $e->getMessage());
        }
    }

    private function getFedexToken(array $creds): string
    {
        $response = Http::asForm()
            ->timeout(10)
            ->post('https://apis.fedex.com/oauth/token', [
                'grant_type'    => 'client_credentials',
                'client_id'     => $creds['api_key'],
                'client_secret' => $creds['api_secret'],
            ]);

        if ($response->failed() || empty($response->json('access_token'))) {
            throw new \RuntimeException('FedEx OAuth failed: ' . $response->body());
        }

        return $response->json('access_token');
    }

    // ══════════════════════════════════════════════════════════════
    // DHL
    // ══════════════════════════════════════════════════════════════

    private function trackDhl(string $tn): array
    {
        $creds = $this->getCourierCreds('dhl');
        if (empty($creds['api_key'])) {
            return $this->unconfigured('DHL', $tn);
        }

        try {
            $response = Http::withHeaders(['DHL-API-Key' => $creds['api_key']])
                ->timeout(15)
                ->get('https://api-eu.dhl.com/track/shipments', [
                    'trackingNumber' => $tn,
                ]);

            if ($response->status() === 404) return $this->notFound('DHL', $tn);
            if ($response->failed()) throw new \RuntimeException('DHL API ' . $response->status());

            $data     = $response->json();
            $shipment = $data['shipments'][0] ?? null;

            if (!$shipment) return $this->notFound('DHL', $tn);

            $status = $shipment['status'] ?? [];
            $events = array_map(fn($e) => [
                'timestamp'   => $e['timestamp'] ?? null,
                'description' => $e['description'] ?? null,
                'location'    => $e['location']['address']['addressLocality'] ?? null,
            ], $shipment['events'] ?? []);

            return [
                'success'         => true,
                'courier'         => 'DHL',
                'tracking_number' => $tn,
                'status'          => $status['description'] ?? 'Unknown',
                'status_code'     => $status['statusCode'] ?? null,
                'is_delivered'    => ($status['statusCode'] ?? '') === 'delivered',
                'estimated_delivery' => $shipment['estimatedTimeOfDelivery'] ?? null,
                'origin'          => $shipment['origin']['address']['addressLocality'] ?? null,
                'destination'     => $shipment['destination']['address']['addressLocality'] ?? null,
                'events'          => $events,
                'raw'             => $shipment,
            ];
        } catch (\Exception $e) {
            Log::error('[IOR DHL Track] ' . $e->getMessage());
            return $this->apiError('DHL', $tn, $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════
    // UPS (OAuth 2.0 + Tracking API 3.0)
    // ══════════════════════════════════════════════════════════════

    private function trackUps(string $tn): array
    {
        $creds = $this->getCourierCreds('ups');
        if (empty($creds['client_id']) || empty($creds['client_secret'])) {
            return $this->unconfigured('UPS', $tn);
        }

        try {
            // Step 1: OAuth token
            $tokenResp = Http::withBasicAuth($creds['client_id'], $creds['client_secret'])
                ->asForm()
                ->timeout(10)
                ->post('https://onlinetools.ups.com/security/v1/oauth/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if ($tokenResp->failed() || empty($tokenResp->json('access_token'))) {
                throw new \RuntimeException('UPS OAuth failed: ' . $tokenResp->body());
            }

            $token = $tokenResp->json('access_token');

            // Step 2: Track
            $response = Http::withToken($token)
                ->withHeaders(['transId' => 'ior-' . uniqid(), 'transactionSrc' => 'IOR'])
                ->timeout(15)
                ->get("https://onlinetools.ups.com/api/track/v1/details/{$tn}");

            if ($response->status() === 404) return $this->notFound('UPS', $tn);
            if ($response->failed()) throw new \RuntimeException('UPS Track API ' . $response->status());

            $data     = $response->json();
            $shipment = $data['trackResponse']['shipment'][0] ?? null;
            if (!$shipment) return $this->notFound('UPS', $tn);

            $pkg     = $shipment['package'][0] ?? [];
            $current = $pkg['currentStatus'] ?? [];
            $events  = array_map(fn($a) => [
                'timestamp'   => ($a['date'] ?? '') . ' ' . ($a['time'] ?? ''),
                'description' => $a['description'] ?? null,
                'location'    => ($a['location']['address']['city'] ?? '') . ', ' . ($a['location']['address']['countryCode'] ?? ''),
            ], $pkg['activity'] ?? []);

            $deliveredCodes = ['011', 'I010'];
            $statusCode     = $current['code'] ?? null;

            return [
                'success'         => true,
                'courier'         => 'UPS',
                'tracking_number' => $tn,
                'status'          => $current['description'] ?? 'Unknown',
                'status_code'     => $statusCode,
                'is_delivered'    => in_array($statusCode, $deliveredCodes),
                'estimated_delivery' => $pkg['deliveryTime']['endTime'] ?? null,
                'origin'          => null,
                'destination'     => $shipment['shipTo']['address']['city'] ?? null,
                'events'          => $events,
                'raw'             => $pkg,
            ];
        } catch (\Exception $e) {
            Log::error('[IOR UPS Track] ' . $e->getMessage());
            return $this->apiError('UPS', $tn, $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════
    // DOMESTIC: Pathao
    // ══════════════════════════════════════════════════════════════

    private function trackPathao(string $tn): array
    {
        $creds = $this->getCourierCreds('pathao');
        if (empty($creds['client_id'])) return $this->unconfigured('Pathao', $tn);

        try {
            // Pathao: get token first, then track
            $tokenResp = Http::timeout(10)->post('https://hermes.pathao.com/api/v1/issue-token', [
                'client_id'     => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
                'username'      => $creds['username']    ?? '',
                'password'      => $creds['password']    ?? '',
                'grant_type'    => 'password',
            ]);

            if ($tokenResp->failed()) throw new \RuntimeException('Pathao auth failed');

            $token = $tokenResp->json('access_token');

            $resp = Http::withToken($token)
                ->timeout(10)
                ->get("https://hermes.pathao.com/api/v1/orders/{$tn}/info");

            if ($resp->failed()) return $this->notFound('Pathao', $tn);

            $data   = $resp->json('data') ?? [];
            $status = $data['delivery_status'] ?? 'Unknown';

            return [
                'success'         => true,
                'courier'         => 'Pathao',
                'tracking_number' => $tn,
                'status'          => $status,
                'status_code'     => null,
                'is_delivered'    => strtolower($status) === 'delivered',
                'estimated_delivery' => null,
                'origin'          => null,
                'destination'     => $data['recipient_address'] ?? null,
                'events'          => [],
                'raw'             => $data,
            ];
        } catch (\Exception $e) {
            Log::error('[IOR Pathao Track] ' . $e->getMessage());
            return $this->apiError('Pathao', $tn, $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════
    // DOMESTIC: Steadfast
    // ══════════════════════════════════════════════════════════════

    private function trackSteadfast(string $tn): array
    {
        $creds = $this->getCourierCreds('steadfast');
        if (empty($creds['api_key'])) return $this->unconfigured('Steadfast', $tn);

        try {
            $resp = Http::withHeaders([
                'Api-Key'    => $creds['api_key'],
                'Secret-Key' => $creds['secret_key'] ?? '',
            ])->timeout(10)
              ->get("https://portal.steadfast.com.bd/api/v1/status-by-consignment/{$tn}");

            if ($resp->failed()) return $this->notFound('Steadfast', $tn);

            $data   = $resp->json();
            $status = $data['delivery_status'] ?? 'Unknown';

            return [
                'success'         => true,
                'courier'         => 'Steadfast',
                'tracking_number' => $tn,
                'status'          => $status,
                'status_code'     => null,
                'is_delivered'    => strtolower($status) === 'delivered',
                'estimated_delivery' => null,
                'origin'          => null,
                'destination'     => null,
                'events'          => [],
                'raw'             => $data,
            ];
        } catch (\Exception $e) {
            Log::error('[IOR Steadfast Track] ' . $e->getMessage());
            return $this->apiError('Steadfast', $tn, $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════
    // DOMESTIC: RedX
    // ══════════════════════════════════════════════════════════════

    private function trackRedX(string $tn): array
    {
        $creds = $this->getCourierCreds('redx');
        if (empty($creds['api_key'])) return $this->unconfigured('RedX', $tn);

        try {
            $resp = Http::withHeaders([
                'API-ACCESS-TOKEN' => 'Bearer ' . $creds['api_key'],
            ])->timeout(10)
              ->get("https://openapi.redx.com.bd/v1.0.0-beta/parcel/{$tn}/tracking");

            if ($resp->failed()) return $this->notFound('RedX', $tn);

            $data   = $resp->json('parcel_info') ?? $resp->json() ?? [];
            $status = $data['parcel_status'] ?? $data['status'] ?? 'Unknown';

            return [
                'success'         => true,
                'courier'         => 'RedX',
                'tracking_number' => $tn,
                'status'          => $status,
                'status_code'     => null,
                'is_delivered'    => strtolower($status) === 'delivered',
                'estimated_delivery' => null,
                'origin'          => null,
                'destination'     => null,
                'events'          => [],
                'raw'             => $data,
            ];
        } catch (\Exception $e) {
            Log::error('[IOR RedX Track] ' . $e->getMessage());
            return $this->apiError('RedX', $tn, $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    private function getCourierCreds(string $code): array
    {
        $raw = \DB::table('ior_courier_configs')
            ->where('courier_code', $code)
            ->value('credentials');
        return json_decode($raw ?? '{}', true) ?? [];
    }

    private function unconfigured(string $name, string $tn): array
    {
        return [
            'success'         => false,
            'courier'         => $name,
            'tracking_number' => $tn,
            'status'          => 'Not Configured',
            'message'         => "$name credentials not set. Go to IOR Settings → Couriers.",
            'is_delivered'    => false,
            'events'          => [],
        ];
    }

    private function notFound(string $name, string $tn): array
    {
        return [
            'success'         => false,
            'courier'         => $name,
            'tracking_number' => $tn,
            'status'          => 'Not Found',
            'message'         => "Tracking number $tn not found in $name system.",
            'is_delivered'    => false,
            'events'          => [],
        ];
    }

    private function apiError(string $name, string $tn, string $error): array
    {
        return [
            'success'         => false,
            'courier'         => $name,
            'tracking_number' => $tn,
            'status'          => 'API Error',
            'message'         => $error,
            'is_delivered'    => false,
            'events'          => [],
        ];
    }

    private function unknownCourier(string $tn, string $code): array
    {
        return [
            'success'         => false,
            'courier'         => $code,
            'tracking_number' => $tn,
            'status'          => 'Unknown Courier',
            'message'         => "Cannot determine courier for tracking number $tn. Please specify courier_code.",
            'is_delivered'    => false,
            'events'          => [],
        ];
    }
}



