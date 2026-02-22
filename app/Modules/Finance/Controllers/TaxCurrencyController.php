<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Finance\{TaxConfig, Currency};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaxCurrencyController extends Controller
{
    public function dashboardStats()
    {
        $defaultCurrency = Currency::getDefault();
        return response()->json([
            'success' => true,
            'data' => [
                'total_taxes'     => TaxConfig::active()->count(),
                'total_currencies'=> Currency::active()->count(),
                'default_currency'=> $defaultCurrency?->code ?? 'USD',
                'tax_types'       => TaxConfig::active()->select('type', DB::raw('COUNT(*) as count'))->groupBy('type')->pluck('count', 'type'),
            ],
        ]);
    }

    // ── Tax CRUD ──────────────────────────────────────────────────────────
    public function getTaxConfigs()
    {
        return response()->json(['success' => true, 'data' => TaxConfig::orderBy('name')->get()]);
    }

    public function storeTax(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'rate'         => 'required|numeric|min:0|max:100',
            'type'         => 'required|in:VAT,GST,sales_tax,custom',
            'applies_to'   => 'nullable|string|max:255',
            'is_inclusive' => 'nullable|boolean',
            'is_active'    => 'nullable|boolean',
            'description'  => 'nullable|string',
        ]);
        return response()->json(['success' => true, 'data' => TaxConfig::create($validated)], 201);
    }

    public function updateTax(Request $request, $id)
    {
        $tax = TaxConfig::findOrFail($id);
        $tax->update($request->validate([
            'name'         => 'sometimes|required|string|max:255',
            'rate'         => 'sometimes|required|numeric|min:0|max:100',
            'is_inclusive' => 'nullable|boolean',
            'is_active'    => 'nullable|boolean',
            'description'  => 'nullable|string',
        ]));
        return response()->json(['success' => true, 'data' => $tax->fresh()]);
    }

    public function destroyTax($id)
    {
        TaxConfig::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Tax rule deleted']);
    }

    public function taxSummary()
    {
        $monthly = DB::table('ec_orders')
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(tax_amount) as total_tax')
            ->whereYear('created_at', now()->year)
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->orderByRaw('YEAR(created_at), MONTH(created_at)')
            ->get();

        return response()->json(['success' => true, 'data' => $monthly]);
    }

    // ── Currency CRUD ─────────────────────────────────────────────────────
    public function getCurrencies()
    {
        return response()->json(['success' => true, 'data' => Currency::orderBy('code')->get()]);
    }

    public function storeCurrency(Request $request)
    {
        $validated = $request->validate([
            'code'                 => 'required|string|max:10|unique:tenant_dynamic.ec_currencies,code',
            'name'                 => 'required|string|max:100',
            'symbol'               => 'required|string|max:10',
            'exchange_rate_to_usd' => 'required|numeric|min:0.000001',
            'is_active'            => 'nullable|boolean',
        ]);
        return response()->json(['success' => true, 'data' => Currency::create($validated)], 201);
    }

    public function updateCurrency(Request $request, $id)
    {
        $currency = Currency::findOrFail($id);
        $currency->update($request->validate([
            'name'                 => 'sometimes|required|string|max:100',
            'symbol'               => 'sometimes|required|string|max:10',
            'exchange_rate_to_usd' => 'sometimes|required|numeric|min:0.000001',
            'is_active'            => 'nullable|boolean',
        ]));
        return response()->json(['success' => true, 'data' => $currency->fresh()]);
    }

    public function setDefaultCurrency($id)
    {
        Currency::where('is_default', true)->update(['is_default' => false]);
        Currency::findOrFail($id)->update(['is_default' => true, 'is_active' => true]);
        return response()->json(['success' => true, 'message' => 'Default currency updated']);
    }

    public function convert(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'from'   => 'required|string|max:10',
            'to'     => 'required|string|max:10',
        ]);

        $result = Currency::convert((float) $request->amount, $request->from, $request->to);
        return response()->json([
            'success' => true,
            'data'    => [
                'from'   => strtoupper($request->from),
                'to'     => strtoupper($request->to),
                'input'  => (float) $request->amount,
                'result' => $result,
            ],
        ]);
    }
}
