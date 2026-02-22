<?php

namespace App\Modules\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CRM\{Customer, LoyaltyProgram, CustomerPoints, LoyaltyTransaction, Coupon, CouponUse};
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();
        if ($request->filled('search')) $query->search($request->search);
        if ($request->filled('active')) $query->where('is_active', $request->boolean('active'));
        return response()->json(['success' => true, 'data' => $query->orderBy('name')->paginate(20)]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'nullable|email|max:255|unique:tenant_dynamic.ec_customers,email',
            'phone'   => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city'    => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'notes'   => 'nullable|string',
        ]);

        $dto = \App\Modules\CRM\DTOs\CustomerDTO::fromRequest($validated);
        $customer = app(\App\Modules\CRM\Actions\StoreCustomerAction::class)->execute($dto);

        return response()->json(['success' => true, 'data' => $customer], 201);
    }

    public function show($id)
    {
        $customer = Customer::with(['orders' => fn($q) => $q->latest()->limit(10), 'points'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $customer]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name'      => 'sometimes|required|string|max:255',
            'email'     => "nullable|email|max:255|unique:tenant_dynamic.ec_customers,email,{$id}",
            'phone'     => 'nullable|string|max:50',
            'address'   => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
            'notes'     => 'nullable|string',
        ]);

        $dto = \App\Modules\CRM\DTOs\CustomerDTO::fromRequest($validated);
        $customer = app(\App\Modules\CRM\Actions\UpdateCustomerAction::class)->execute((int)$id, $dto);

        return response()->json(['success' => true, 'data' => $customer]);
    }

    public function destroy($id)
    {
        Customer::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Customer deleted']);
    }
}
