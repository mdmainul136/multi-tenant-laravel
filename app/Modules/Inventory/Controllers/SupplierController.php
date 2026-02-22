<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Inventory\{Supplier, PurchaseOrder, PurchaseOrderItem};
use App\Models\Ecommerce\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function stats()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_suppliers'  => Supplier::count(),
                'active_suppliers' => Supplier::active()->count(),
                'total_pos'        => PurchaseOrder::count(),
                'pending_pos'      => PurchaseOrder::pending()->count(),
                'total_spend'      => (float) PurchaseOrder::whereIn('status', ['received', 'partial'])->sum('total_amount'),
                'by_status'        => PurchaseOrder::select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->pluck('count', 'status'),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $query = Supplier::withCount(['purchaseOrders as po_count']);
        if ($request->filled('search')) $query->search($request->search);
        if ($request->filled('status')) $query->where('status', $request->status);
        return response()->json(['success' => true, 'data' => $query->orderBy('name')->paginate(20)]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'contact_name'  => 'nullable|string|max:255',
            'email'         => 'nullable|email|max:255',
            'phone'         => 'nullable|string|max:50',
            'website'       => 'nullable|url|max:255',
            'address'       => 'nullable|string|max:500',
            'city'          => 'nullable|string|max:100',
            'country'       => 'nullable|string|max:100',
            'payment_terms' => 'nullable|string|max:255',
            'lead_time_days'=> 'nullable|integer|min:0',
            'currency'      => 'nullable|string|max:10',
            'status'        => 'nullable|in:active,inactive',
            'notes'         => 'nullable|string',
        ]);
        return response()->json(['success' => true, 'data' => Supplier::create($validated)], 201);
    }

    public function show($id)
    {
        $supplier = Supplier::with(['purchaseOrders' => fn($q) => $q->latest()->limit(10)])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $supplier]);
    }

    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->update($request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'contact_name'  => 'nullable|string|max:255',
            'email'         => 'nullable|email|max:255',
            'phone'         => 'nullable|string|max:50',
            'payment_terms' => 'nullable|string|max:255',
            'lead_time_days'=> 'nullable|integer|min:0',
            'status'        => 'nullable|in:active,inactive',
            'notes'         => 'nullable|string',
        ]));
        return response()->json(['success' => true, 'data' => $supplier->fresh()]);
    }

    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);
        if ($supplier->purchaseOrders()->whereIn('status', ['sent', 'partial'])->exists()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete supplier with open POs'], 422);
        }
        $supplier->delete();
        return response()->json(['success' => true, 'message' => 'Supplier deleted']);
    }
}
