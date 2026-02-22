<?php

namespace App\Modules\Ecommerce\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ecommerce\TaxConfig;
use App\Models\Ecommerce\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaxCurrencyController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // TAX CONFIGURATIONS
    // ══════════════════════════════════════════════════════════════

    /**
     * List all tax configurations
     */
    public function getTaxConfigs(Request $request)
    {
        $query = TaxConfig::query();

        if ($request->boolean('active_only')) {
            $query->active();
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return response()->json(['success' => true, 'data' => $query->orderBy('name')->get()]);
    }

    /**
     * Create a new tax configuration
     */
    public function storeTax(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'rate'          => 'required|numeric|min:0|max:100',
            'type'          => 'required|in:VAT,GST,sales_tax,custom',
            'applies_to'    => 'required|in:all,category,product',
            'category_name' => 'nullable|string|max:255',
            'is_inclusive'  => 'nullable|boolean',
            'is_active'     => 'nullable|boolean',
        ]);

        $taxConfig = TaxConfig::create($validated);

        return response()->json(['success' => true, 'message' => 'Tax config created', 'data' => $taxConfig], 201);
    }

    /**
     * Update a tax configuration
     */
    public function updateTax(Request $request, $id)
    {
        $taxConfig = TaxConfig::findOrFail($id);

        $validated = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'description'   => 'nullable|string',
            'rate'          => 'sometimes|required|numeric|min:0|max:100',
            'type'          => 'sometimes|required|in:VAT,GST,sales_tax,custom',
            'applies_to'    => 'sometimes|required|in:all,category,product',
            'category_name' => 'nullable|string|max:255',
            'is_inclusive'  => 'nullable|boolean',
            'is_active'     => 'nullable|boolean',
        ]);

        $taxConfig->update($validated);

        return response()->json(['success' => true, 'message' => 'Tax config updated', 'data' => $taxConfig->fresh()]);
    }

    /**
     * Delete a tax configuration
     */
    public function destroyTax($id)
    {
        TaxConfig::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Tax config deleted']);
    }

    /**
     * Tax collection summary — grouped by month or period
     */
    public function taxSummary(Request $request)
    {
        $period  = $request->get('period', 'month');   // month / year / all
        $taxRate = TaxConfig::active()->forAll()->first()?->rate ?? 0;

        $query = DB::connection('tenant_dynamic')
                   ->table('ec_orders')
                   ->where('status', '!=', 'cancelled')
                   ->whereNotNull('tax');

        if ($period === 'month') {
            $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
        } elseif ($period === 'year') {
            $query->whereYear('created_at', now()->year);
        }

        $summary = $query->select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as period'),
            DB::raw('COUNT(*) as order_count'),
            DB::raw('SUM(tax) as tax_collected'),
            DB::raw('SUM(total) as revenue')
        )->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))->orderByDesc('period')->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'summary'      => $summary,
                'active_rate'  => (float) $taxRate,
                'total_collected' => (float) $summary->sum('tax_collected'),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // CURRENCY MANAGEMENT
    // ══════════════════════════════════════════════════════════════

    /**
     * List all currencies
     */
    public function getCurrencies(Request $request)
    {
        $query = Currency::query();
        if ($request->boolean('active_only')) $query->active();
        return response()->json(['success' => true, 'data' => $query->orderBy('code')->get()]);
    }

    /**
     * Add a new currency
     */
    public function storeCurrency(Request $request)
    {
        $validated = $request->validate([
            'code'                 => 'required|string|max:10|unique:tenant_dynamic.ec_currencies,code',
            'name'                 => 'required|string|max:100',
            'symbol'               => 'required|string|max:10',
            'exchange_rate_to_usd' => 'required|numeric|min:0.0000001',
            'is_active'            => 'nullable|boolean',
        ]);

        $currency = Currency::create($validated);

        return response()->json(['success' => true, 'message' => 'Currency added', 'data' => $currency], 201);
    }

    /**
     * Update currency (rate or status)
     */
    public function updateCurrency(Request $request, $id)
    {
        $currency  = Currency::findOrFail($id);
        $validated = $request->validate([
            'name'                 => 'sometimes|required|string|max:100',
            'symbol'               => 'sometimes|required|string|max:10',
            'exchange_rate_to_usd' => 'sometimes|required|numeric|min:0.0000001',
            'is_active'            => 'nullable|boolean',
        ]);

        $validated['rates_updated_at'] = now();
        $currency->update($validated);

        return response()->json(['success' => true, 'message' => 'Currency updated', 'data' => $currency->fresh()]);
    }

    /**
     * Set a currency as the store default
     */
    public function setDefaultCurrency($id)
    {
        $currency = Currency::findOrFail($id);
        if (!$currency->is_active) {
            return response()->json(['success' => false, 'message' => 'Cannot set an inactive currency as default'], 422);
        }
        $currency->setAsDefault();
        return response()->json(['success' => true, 'message' => "{$currency->code} set as default currency"]);
    }

    /**
     * Convert amount between currencies
     * POST body: { amount, from, to }
     */
    public function convert(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'from'   => 'required|string|max:10',
            'to'     => 'required|string|max:10',
        ]);

        $fromCurrency = Currency::where('code', strtoupper($request->from))->firstOrFail();
        $toCurrency   = Currency::where('code', strtoupper($request->to))->firstOrFail();

        $converted = $fromCurrency->convertTo((float) $request->amount, $toCurrency);

        return response()->json([
            'success' => true,
            'data'    => [
                'original_amount'  => (float) $request->amount,
                'original_currency'=> $fromCurrency->code,
                'converted_amount' => $converted,
                'target_currency'  => $toCurrency->code,
                'target_symbol'    => $toCurrency->symbol,
                'formatted'        => $toCurrency->getFormattedAmount($converted),
                'rate'             => round($fromCurrency->exchange_rate_to_usd / $toCurrency->exchange_rate_to_usd, 8),
            ],
        ]);
    }

    /**
     * KPI widget for Tax & Currency dashboard
     */
    public function dashboardStats()
    {
        $now = now();

        $taxMtd = DB::connection('tenant_dynamic')
                    ->table('ec_orders')
                    ->whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year)
                    ->where('status', '!=', 'cancelled')
                    ->sum('tax');

        return response()->json([
            'success' => true,
            'data'    => [
                'active_tax_configs' => TaxConfig::active()->count(),
                'active_currencies'  => Currency::active()->count(),
                'default_currency'   => Currency::default()->first(),
                'tax_collected_mtd'  => (float) $taxMtd,
                'active_vat_rate'    => (float) (TaxConfig::active()->where('type', 'VAT')->first()?->rate ?? 0),
            ],
        ]);
    }
}
