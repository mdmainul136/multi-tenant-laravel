<?php

namespace App\Modules\CrossBorderIOR\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CrossBorderIOR\IorCustomsRate;
use App\Models\CrossBorderIOR\IorSetting;
use App\Models\CrossBorderIOR\IorShippingSettings;
use App\Modules\CrossBorderIOR\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private ExchangeRateService $fx) {}

    /**
     * GET /ior/settings
     * All IOR settings grouped by category.
     */
    public function index(): JsonResponse
    {
        $settings = IorSetting::allAsMap();

        // Mask sensitive keys
        $masked = ['bkash_app_secret', 'bkash_password', 'sslcommerz_store_pass', 'apify_api_token'];
        foreach ($masked as $k) {
            if (isset($settings[$k]) && $settings[$k]) {
                $settings[$k] = '●●●●●●●●';
            }
        }

        return response()->json(['success' => true, 'data' => $settings]);
    }

    /**
     * PUT /ior/settings
     * Batch update multiple settings.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string|max:5000',
        ]);

        foreach ($data['settings'] as $key => $value) {
            // Skip masked values (don't overwrite if user didn't change)
            if ($value === '●●●●●●●●') continue;

            $group = match (true) {
                str_starts_with($key, 'bkash_')       => 'payment',
                str_starts_with($key, 'sslcommerz_')  => 'payment',
                str_starts_with($key, 'stripe_')      => 'payment',
                str_starts_with($key, 'apify_')       => 'scraper',
                in_array($key, ['default_exchange_rate', 'default_profit_margin', 'advance_payment_percent', 'last_exchange_rate', 'exchange_buffer_percent']) => 'pricing',
                in_array($key, ['admin_notification_email', 'support_email']) => 'email',
                default => 'general',
            };

            IorSetting::set($key, $value, $group);
        }

        return response()->json(['success' => true, 'message' => 'Settings saved.']);
    }

    // ════════════════════════════════════════
    // CUSTOMS RATES
    // ════════════════════════════════════════

    /**
     * GET /ior/settings/customs-rates
     */
    public function customsRates(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => IorCustomsRate::orderBy('category')->get(),
        ]);
    }

    /**
     * PUT /ior/settings/customs-rates/{id}
     */
    public function updateCustomsRate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'rate_percentage' => 'required|numeric|min:0|max:500',
            'is_active'       => 'boolean',
        ]);

        $rate = IorCustomsRate::findOrFail($id);
        $rate->update($data);

        return response()->json(['success' => true, 'data' => $rate]);
    }

    // ════════════════════════════════════════
    // EXCHANGE RATE
    // ════════════════════════════════════════

    /**
     * GET /ior/settings/exchange-rate
     * Returns current rate with source and last-updated timestamp.
     */
    public function exchangeRate(): JsonResponse
    {
        $rate       = $this->fx->getUsdToBdt();
        $storedRate = IorSetting::get('last_exchange_rate', null);
        $updatedAt  = IorSetting::where('key', 'last_exchange_rate')->value('updated_at');

        return response()->json([
            'success'       => true,
            'rate'          => $rate,
            'currency_pair' => 'USD/BDT',
            'source'        => 'open.er-api.com',
            'updated_at'    => $updatedAt ?? now()->toISOString(),
            'is_live'       => true,
        ]);
    }

    /**
     * POST /ior/settings/exchange-rate/refresh
     */
    public function refreshExchangeRate(): JsonResponse
    {
        $this->fx->clearCache();
        $rate = $this->fx->getUsdToBdt(forceRefresh: true);

        IorSetting::set('last_exchange_rate', (string) $rate, 'pricing');

        return response()->json([
            'success' => true,
            'message' => 'Exchange rate refreshed.',
            'rate'    => $rate,
        ]);
    }

    // ════════════════════════════════════════
    // SHIPPING RATES (air / sea)
    // ════════════════════════════════════════

    /**
     * GET /ior/settings/shipping-rates
     * Returns all shipping methods with rates and display metadata.
     */
    public function shippingRates(): JsonResponse
    {
        $rates = IorShippingSettings::orderBy('shipping_method')->get();

        return response()->json([
            'success' => true,
            'data'    => $rates,
        ]);
    }

    /**
     * PUT /ior/settings/shipping-rates/{id}
     * Update a shipping rate row (rate_per_kg, min_charge, is_active, delivery_time, weight_range, description).
     */
    public function updateShippingRate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'rate_per_kg'   => 'sometimes|numeric|min:0',
            'min_charge'    => 'sometimes|numeric|min:0',
            'is_active'     => 'sometimes|boolean',
            'delivery_time' => 'sometimes|string|max:50',
            'weight_range'  => 'sometimes|string|max:50',
            'description'   => 'sometimes|string|max:200',
        ]);

        $rate = IorShippingSettings::findOrFail($id);
        $rate->update($data);

        return response()->json([
            'success' => true,
            'message' => ucfirst($rate->shipping_method) . ' freight rate updated.',
            'data'    => $rate->fresh(),
        ]);
    }

    // ════════════════════════════════════════
    // COURIER CONFIGS
    // ════════════════════════════════════════

    /**
     * GET /ior/settings/couriers
     */
    public function couriers(): JsonResponse
    {
        $couriers = \DB::table('ior_courier_configs')->get()->map(function ($c) {
            $creds = json_decode($c->credentials, true) ?? [];
            // Mask non-empty values
            $masked = array_map(fn($v) => $v ? '●●●●●●●●' : '', $creds);
            return array_merge((array) $c, ['credentials' => $masked]);
        });

        return response()->json(['success' => true, 'data' => $couriers]);
    }

    /**
     * PUT /ior/settings/couriers/{code}
     */
    public function updateCourier(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'credentials' => 'required|array',
            'is_active'   => 'boolean',
        ]);

        // Merge new credentials (skip masked)
        $existing = json_decode(
            \DB::table('ior_courier_configs')->where('courier_code', $code)->value('credentials') ?? '{}',
            true
        );

        $merged = array_map(
            fn($new, $old) => ($new === '●●●●●●●●' ? $old : $new),
            $data['credentials'],
            $existing
        );

        \DB::table('ior_courier_configs')->where('courier_code', $code)->update([
            'credentials' => json_encode($merged),
            'is_active'   => $data['is_active'] ?? false,
            'updated_at'  => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Courier config saved.']);
    }
}



