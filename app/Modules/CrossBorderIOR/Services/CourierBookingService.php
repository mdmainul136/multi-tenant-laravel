<?php

namespace App\Modules\CrossBorderIOR\Services;

use App\Models\CrossBorderIOR\IorForeignOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CourierBookingService
 *
 * Creates delivery parcels with domestic (Pathao, Steadfast, RedX)
 * and international (FedEx, DHL) couriers — ported from Supabase `book-courier`.
 *
 * After a successful booking the order row is updated with:
 *   - tracking_number
 *   - courier_code
 *   - order_status  → 'shipped'
 *
 * All methods return a normalised array:
 * [
 *   'success'          => bool,
 *   'courier'          => 'Pathao' | 'Steadfast' | 'RedX' | 'FedEx' | 'DHL',
 *   'tracking_number'  => string | null,
 *   'courier_order_id' => string | null,
 *   'tracking_url'     => string | null,
 *   'label_url'        => string | null   // FedEx / DHL only
 *   'message'          => string,
 * ]
 */
class CourierBookingService
{
    // ──────────────────────────────────────────────────────────────
    // MAIN DISPATCH
    // ──────────────────────────────────────────────────────────────

    /**
     * Book a courier for an IOR order.
     *
     * @param  IorForeignOrder $order
     * @param  string          $courierCode   pathao|steadfast|redx|fedex|dhl
     * @return array
     */
    public function book(IorForeignOrder $order, string $courierCode): array
    {
        $creds = $this->getCourierCreds($courierCode);

        Log::info("[IOR Booking] Booking courier={$courierCode} order={$order->order_number}");

        $result = match (strtolower($courierCode)) {
            'pathao'    => $this->bookPathao($creds, $order),
            'steadfast' => $this->bookSteadfast($creds, $order),
            'redx'      => $this->bookRedX($creds, $order),
            'fedex'     => $this->bookFedEx($creds, $order),
            'dhl'       => $this->bookDHL($creds, $order),
            default     => ['success' => false, 'message' => "Unsupported courier: {$courierCode}"],
        };

        // On success — persist to order
        if ($result['success']) {
            $order->update([
                'tracking_number' => $result['tracking_number'],
                'courier_code'    => $courierCode,
                'order_status'    => IorForeignOrder::STATUS_SHIPPED,
            ]);

            Log::info("[IOR Booking] ✅ Booked {$courierCode} for order {$order->order_number}. Tracking: {$result['tracking_number']}");
        } else {
            Log::warning("[IOR Booking] ❌ Failed courier={$courierCode} order={$order->order_number}: " . ($result['message'] ?? ''));
        }

        return $result;
    }

    // ══════════════════════════════════════════════════════════════
    // DOMESTIC: Pathao
    // ══════════════════════════════════════════════════════════════

    private function bookPathao(array $creds, IorForeignOrder $order): array
    {
        if (empty($creds['client_id'])) {
            return $this->unconfigured('Pathao');
        }

        try {
            // Step 1: OAuth token
            $baseUrl = $creds['api_base_url'] ?? 'https://api-hermes.pathaointernal.com';

            $tokenResp = Http::timeout(10)->post("{$baseUrl}/aladdin/api/v1/issue-token", [
                'client_id'     => $creds['client_id'],
                'client_secret' => $creds['client_secret'] ?? '',
                'grant_type'    => 'client_credentials',
            ]);

            if ($tokenResp->failed() || empty($tokenResp->json('access_token'))) {
                throw new \RuntimeException('Pathao auth failed: ' . $tokenResp->body());
            }

            $token = $tokenResp->json('access_token');

            // Step 2: Create order
            $response = Http::withToken($token)->timeout(15)->post("{$baseUrl}/aladdin/api/v1/orders", [
                'store_id'             => $creds['store_id'] ?? null,
                'merchant_order_id'    => $order->order_number,
                'recipient_name'       => $order->shipping_name    ?? $order->customer_name,
                'recipient_phone'      => $order->shipping_phone   ?? $order->customer_phone,
                'recipient_address'    => $order->shipping_address ?? '',
                'recipient_city'       => $order->shipping_city    ?? 'Dhaka',
                'recipient_zone'       => $order->shipping_city    ?? 'Dhaka',
                'delivery_type'        => 48,  // Normal delivery
                'item_type'            => 2,   // Parcel
                'item_quantity'        => 1,
                'item_weight'          => max(0.5, (float) ($order->product_weight_kg ?? 0.5)),
                'amount_to_collect'    => $order->payment_method === 'cod' ? (float) $order->total_bdt : 0,
                'item_description'     => "IOR Order {$order->order_number}",
            ]);

            $result = $response->json();

            if (!$response->ok()) {
                throw new \RuntimeException($result['message'] ?? 'Pathao booking failed');
            }

            $consignmentId = $result['data']['consignment_id'] ?? null;

            return [
                'success'          => true,
                'courier'          => 'Pathao',
                'tracking_number'  => $consignmentId,
                'courier_order_id' => $consignmentId,
                'tracking_url'     => "https://merchant.pathao.com/tracking?consignment_id={$consignmentId}",
                'label_url'        => null,
                'message'          => 'Pathao parcel created successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('[IOR Pathao Book] ' . $e->getMessage());
            return $this->apiError('Pathao', $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════
    // DOMESTIC: Steadfast
    // ══════════════════════════════════════════════════════════════

    private function bookSteadfast(array $creds, IorForeignOrder $order): array
    {
        if (empty($creds['api_key'])) {
            return $this->unconfigured('Steadfast');
        }

        try {
            $response = Http::withHeaders([
                'Api-Key'    => $creds['api_key'],
                'Secret-Key' => $creds['secret_key'] ?? '',
                'Content-Type' => 'application/json',
            ])->timeout(15)->post('https://portal.steadfast.com.bd/api/v1/create_order', [
                'invoice'          => $order->order_number,
                'recipient_name'   => $order->shipping_name    ?? $order->customer_name,
                'recipient_phone'  => $order->shipping_phone   ?? $order->customer_phone,
                'recipient_address'=> $order->shipping_address ?? '',
                'cod_amount'       => $order->payment_method === 'cod' ? (float) $order->total_bdt : 0,
                'note'             => $order->notes ?? "IOR Order {$order->order_number}",
            ]);

            $result = $response->json();

            if (($result['status'] ?? 0) !== 200) {
                throw new \RuntimeException($result['message'] ?? 'Steadfast booking failed');
            }

            $trackingCode    = $result['consignment']['tracking_code']   ?? null;
            $consignmentId   = $result['consignment']['consignment_id']  ?? null;

            return [
                'success'          => true,
                'courier'          => 'Steadfast',
                'tracking_number'  => $trackingCode,
                'courier_order_id' => $consignmentId,
                'tracking_url'     => "https://steadfast.com.bd/t/{$trackingCode}",
                'label_url'        => null,
                'message'          => 'Steadfast parcel created successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('[IOR Steadfast Book] ' . $e->getMessage());
            return $this->apiError('Steadfast', $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════
    // DOMESTIC: RedX
    // ══════════════════════════════════════════════════════════════

    private function bookRedX(array $creds, IorForeignOrder $order): array
    {
        if (empty($creds['api_key'])) {
            return $this->unconfigured('RedX');
        }

        try {
            $response = Http::withHeaders([
                'API-ACCESS-TOKEN' => 'Bearer ' . $creds['api_key'],
                'Content-Type'     => 'application/json',
            ])->timeout(15)->post('https://openapi.redx.com.bd/v1.0.0-beta/parcel', [
                'customer_name'         => $order->shipping_name    ?? $order->customer_name,
                'customer_phone'        => $order->shipping_phone   ?? $order->customer_phone,
                'delivery_area'         => $order->shipping_city    ?? 'Dhaka',
                'delivery_area_id'      => null,
                'customer_address'      => $order->shipping_address ?? '',
                'merchant_invoice_id'   => $order->order_number,
                'cash_collection_amount'=> $order->payment_method === 'cod' ? (string) $order->total_bdt : '0',
                'parcel_weight'         => max(500, (int) (($order->product_weight_kg ?? 0.5) * 1000)),
                'instruction'           => $order->notes ?? '',
                'value'                 => (string) ($order->total_bdt ?? 0),
            ]);

            $result = $response->json();

            if (!$response->ok()) {
                throw new \RuntimeException($result['message'] ?? 'RedX booking failed');
            }

            $trackingId = $result['tracking_id'] ?? null;
            $parcelId   = $result['parcel_id']   ?? null;

            return [
                'success'          => true,
                'courier'          => 'RedX',
                'tracking_number'  => $trackingId,
                'courier_order_id' => $parcelId,
                'tracking_url'     => "https://redx.com.bd/track-parcel/?trackingId={$trackingId}",
                'label_url'        => null,
                'message'          => 'RedX parcel created successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('[IOR RedX Book] ' . $e->getMessage());
            return $this->apiError('RedX', $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════
    // INTERNATIONAL: FedEx (Ship v1)
    // ══════════════════════════════════════════════════════════════

    private function bookFedEx(array $creds, IorForeignOrder $order): array
    {
        if (empty($creds['api_key']) || empty($creds['api_secret'])) {
            return $this->unconfigured('FedEx');
        }

        try {
            // Step 1: OAuth token
            $tokenResp = Http::asForm()->timeout(10)->post('https://apis.fedex.com/oauth/token', [
                'grant_type'    => 'client_credentials',
                'client_id'     => $creds['api_key'],
                'client_secret' => $creds['api_secret'],
            ]);

            if ($tokenResp->failed() || empty($tokenResp->json('access_token'))) {
                throw new \RuntimeException('FedEx OAuth failed: ' . $tokenResp->body());
            }

            $token  = $tokenResp->json('access_token');
            $weight = max(0.5, (float) ($order->product_weight_kg ?? 0.5));

            // Step 2: Create shipment
            $response = Http::withToken($token)->timeout(20)->post('https://apis.fedex.com/ship/v1/shipments', [
                'labelResponseOptions' => 'URL_ONLY',
                'requestedShipment'    => [
                    'shipper' => [
                        'address' => [
                            'streetLines'          => [$creds['shipper_address'] ?? 'Store Address'],
                            'city'                 => 'Dhaka',
                            'stateOrProvinceCode'  => 'DH',
                            'postalCode'           => '1000',
                            'countryCode'          => 'BD',
                        ],
                        'contact' => [
                            'personName'  => $creds['shipper_name']  ?? 'IOR Store',
                            'phoneNumber' => $creds['shipper_phone'] ?? '+8801000000000',
                        ],
                    ],
                    'recipients' => [[
                        'address' => [
                            'streetLines' => [$order->shipping_address ?? ''],
                            'city'        => $order->shipping_city       ?? 'Dhaka',
                            'postalCode'  => $order->shipping_postal_code ?? '1000',
                            'countryCode' => 'BD',
                        ],
                        'contact' => [
                            'personName'  => $order->shipping_name  ?? $order->customer_name,
                            'phoneNumber' => $order->shipping_phone ?? $order->customer_phone,
                        ],
                    ]],
                    'serviceType'    => 'INTERNATIONAL_PRIORITY',
                    'packagingType'  => 'YOUR_PACKAGING',
                    'pickupType'     => 'USE_SCHEDULED_PICKUP',
                    'shippingChargesPayment' => ['paymentType' => 'SENDER'],
                    'requestedPackageLineItems' => [[
                        'weight' => ['value' => $weight, 'units' => 'KG'],
                    ]],
                ],
            ]);

            $result = $response->json();

            if (!$response->ok()) {
                throw new \RuntimeException($result['errors'][0]['message'] ?? 'FedEx booking failed');
            }

            $shipment  = $result['output']['transactionShipments'][0] ?? [];
            $trackNum  = $shipment['masterTrackingNumber'] ?? null;
            $labelUrl  = $shipment['pieceResponses'][0]['packageDocuments'][0]['url'] ?? null;

            return [
                'success'          => true,
                'courier'          => 'FedEx',
                'tracking_number'  => $trackNum,
                'courier_order_id' => $shipment['shipmentId'] ?? null,
                'tracking_url'     => "https://www.fedex.com/fedextrack/?trknbr={$trackNum}",
                'label_url'        => $labelUrl,
                'message'          => 'FedEx shipment created successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('[IOR FedEx Book] ' . $e->getMessage());
            return $this->apiError('FedEx', $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════
    // INTERNATIONAL: DHL Express
    // ══════════════════════════════════════════════════════════════

    private function bookDHL(array $creds, IorForeignOrder $order): array
    {
        if (empty($creds['api_key'])) {
            return $this->unconfigured('DHL');
        }

        try {
            $auth   = base64_encode("{$creds['api_key']}:{$creds['api_secret']}");
            $weight = max(0.5, (float) ($order->product_weight_kg ?? 0.5));

            $response = Http::withHeaders([
                'Authorization' => "Basic {$auth}",
                'Content-Type'  => 'application/json',
            ])->timeout(20)->post('https://express.api.dhl.com/mydhlapi/shipments', [
                'plannedShippingDateAndTime' => now()->toIso8601String(),
                'pickup'                     => ['isRequested' => false],
                'productCode'                => 'P',
                'accounts'                   => [['typeCode' => 'shipper', 'number' => $creds['account_id'] ?? '']],
                'customerDetails' => [
                    'shipperDetails' => [
                        'postalAddress' => [
                            'postalCode'  => '1000',
                            'cityName'    => 'Dhaka',
                            'countryCode' => 'BD',
                            'addressLine1'=> $creds['shipper_address'] ?? 'Store Address',
                        ],
                        'contactInformation' => [
                            'phone'       => $creds['shipper_phone'] ?? '+8801000000000',
                            'companyName' => $creds['shipper_name'] ?? 'IOR Store',
                            'fullName'    => $creds['shipper_name'] ?? 'IOR Store',
                        ],
                    ],
                    'receiverDetails' => [
                        'postalAddress' => [
                            'postalCode'  => $order->shipping_postal_code ?? '1000',
                            'cityName'    => $order->shipping_city        ?? 'Dhaka',
                            'countryCode' => 'BD',
                            'addressLine1'=> $order->shipping_address     ?? '',
                        ],
                        'contactInformation' => [
                            'phone'       => $order->shipping_phone ?? $order->customer_phone,
                            'companyName' => $order->shipping_name  ?? $order->customer_name,
                            'fullName'    => $order->shipping_name  ?? $order->customer_name,
                        ],
                    ],
                ],
                'content' => [
                    'packages' => [[
                        'weight'     => $weight,
                        'dimensions' => ['length' => 20, 'width' => 15, 'height' => 10],
                    ]],
                    'isCustomsDeclarable'  => true,
                    'declaredValue'        => (float) ($order->total_bdt ?? 0),
                    'declaredValueCurrency'=> 'BDT',
                    'description'          => "IOR Order {$order->order_number}",
                ],
            ]);

            $result = $response->json();

            if (!$response->ok()) {
                throw new \RuntimeException($result['detail'] ?? 'DHL booking failed');
            }

            $trackNum = $result['shipmentTrackingNumber']      ?? null;
            $dispatchNum = $result['dispatchConfirmationNumber'] ?? null;

            return [
                'success'          => true,
                'courier'          => 'DHL',
                'tracking_number'  => $trackNum,
                'courier_order_id' => $dispatchNum,
                'tracking_url'     => "https://www.dhl.com/en/express/tracking.html?AWB={$trackNum}",
                'label_url'        => $result['documents'][0]['content'] ?? null,
                'message'          => 'DHL shipment created successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('[IOR DHL Book] ' . $e->getMessage());
            return $this->apiError('DHL', $e->getMessage());
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

    private function unconfigured(string $name): array
    {
        return [
            'success'          => false,
            'courier'          => $name,
            'tracking_number'  => null,
            'courier_order_id' => null,
            'tracking_url'     => null,
            'label_url'        => null,
            'message'          => "{$name} credentials not configured. Go to IOR Settings → Couriers.",
        ];
    }

    private function apiError(string $name, string $error): array
    {
        return [
            'success'          => false,
            'courier'          => $name,
            'tracking_number'  => null,
            'courier_order_id' => null,
            'tracking_url'     => null,
            'label_url'        => null,
            'message'          => "API error ({$name}): {$error}",
        ];
    }
}
