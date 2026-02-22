<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\FlashSales\FlashSale;
use Illuminate\Support\Facades\Validator;

class FlashSaleController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->header('X-Tenant-Id');
        $sales = FlashSale::where('tenant_id', $tenantId)->orderByDesc('created_at')->get();
        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $tenantId = $request->header('X-Tenant-Id');
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'product_ids' => 'required|array',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $sale = FlashSale::create(array_merge($request->all(), [
            'tenant_id' => $tenantId
        ]));

        return response()->json([
            'message' => 'Flash sale scheduled successfully.',
            'sale' => $sale
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $request->header('X-Tenant-Id');
        $sale = FlashSale::where('tenant_id', $tenantId)->findOrFail($id);
        
        $sale->update($request->all());

        return response()->json([
            'message' => 'Flash sale updated.',
            'sale' => $sale
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $request->header('X-Tenant-Id');
        $sale = FlashSale::where('tenant_id', $tenantId)->findOrFail($id);
        $sale->delete();

        return response()->json(['message' => 'Flash sale deleted.']);
    }

    public function getActive(Request $request)
    {
        $tenantId = $request->header('X-Tenant-Id');
        $now = now();
        
        $sales = FlashSale::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->get();

        return response()->json($sales);
    }
}

