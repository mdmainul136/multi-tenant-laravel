<?php

namespace App\Modules\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CRM\{LoyaltyProgram, CustomerPoints, LoyaltyTransaction, Coupon, Customer};
use Illuminate\Http\Request;

class LoyaltyCouponController extends Controller
{
    // ── Loyalty Program ────────────────────────────────────────────────────
    public function getProgram()
    {
        $program = LoyaltyProgram::first() ?? LoyaltyProgram::create([
            'name'                    => 'Loyalty Rewards',
            'points_per_currency_unit'=> 1.00,
            'min_redeem_points'       => 100,
            'point_value'             => 0.01,
            'is_active'               => true,
        ]);
        return response()->json(['success' => true, 'data' => $program]);
    }

    public function updateProgram(Request $request)
    {
        $program = LoyaltyProgram::first() ?? new LoyaltyProgram();
        $program->fill($request->validate([
            'name'                    => 'sometimes|required|string|max:255',
            'points_per_currency_unit'=> 'sometimes|required|numeric|min:0',
            'min_redeem_points'       => 'sometimes|required|integer|min:1',
            'point_value'             => 'sometimes|required|numeric|min:0',
            'points_expiry_days'      => 'nullable|integer|min:0',
            'is_active'               => 'nullable|boolean',
            'terms'                   => 'nullable|string',
        ]))->save();
        return response()->json(['success' => true, 'data' => $program]);
    }

    // ── Stats ─────────────────────────────────────────────────────────────
    public function loyaltyStats()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_issued'   => (int) LoyaltyTransaction::where('type', 'earn')->sum('points'),
                'total_redeemed' => (int) LoyaltyTransaction::where('type', 'redeem')
                                            ->sum(\Illuminate\Support\Facades\DB::raw('ABS(points)')),
                'active_members' => CustomerPoints::where('points_balance', '>', 0)->count(),
                'mtd_earned'     => (int) LoyaltyTransaction::where('type', 'earn')
                                            ->whereMonth('created_at', now()->month)->sum('points'),
                'top_customers'  => CustomerPoints::with('customer')->orderByDesc('lifetime_earned')->limit(5)->get(),
            ],
        ]);
    }

    // ── Customer Points ───────────────────────────────────────────────────
    public function getCustomerPoints(Request $request)
    {
        $query = CustomerPoints::with('customer')->orderByDesc('points_balance');
        if ($request->filled('search')) {
            $query->whereHas('customer', fn($q) => $q
                ->where('name', 'LIKE', "%{$request->search}%")
                ->orWhere('email', 'LIKE', "%{$request->search}%"));
        }
        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
    }

    public function getCustomerBalance($customerId)
    {
        $customer = Customer::findOrFail($customerId);
        $points   = CustomerPoints::firstOrCreate(
            ['customer_id' => $customerId],
            ['points_balance' => 0, 'lifetime_earned' => 0, 'lifetime_redeemed' => 0]
        );
        return response()->json([
            'success' => true,
            'data'    => [
                'customer'     => $customer,
                'points'       => $points,
                'transactions' => LoyaltyTransaction::where('customer_id', $customerId)
                                    ->orderByDesc('created_at')->limit(20)->get(),
            ],
        ]);
    }

    public function adjustPoints(Request $request, $customerId)
    {
        Customer::findOrFail($customerId);
        $request->validate(['points' => 'required|integer|not_in:0', 'description' => 'required|string|max:255']);

        $cp = CustomerPoints::firstOrCreate(['customer_id' => $customerId], ['points_balance' => 0]);

        if ($request->points < 0 && abs($request->points) > $cp->points_balance) {
            return response()->json(['success' => false, 'message' => 'Insufficient points balance'], 422);
        }

        $tx = $cp->addPoints($request->points, 'adjust', $request->description);
        return response()->json(['success' => true, 'data' => $tx]);
    }

    // ── Coupons ───────────────────────────────────────────────────────────
    public function getCoupons(Request $request)
    {
        $query = Coupon::withCount('uses');
        if ($request->boolean('active_only')) $query->active();
        if ($request->filled('type'))         $query->where('type', $request->type);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('code', 'LIKE', "%{$s}%")->orWhere('name', 'LIKE', "%{$s}%"));
        }
        return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->paginate(20)]);
    }

    public function storeCoupon(Request $request)
    {
        $validated = $request->validate([
            'code'                  => 'required|string|max:50|unique:tenant_dynamic.ec_coupons,code',
            'name'                  => 'required|string|max:255',
            'type'                  => 'required|in:fixed,percent,free_shipping,buy_x_get_y',
            'value'                 => 'required|numeric|min:0',
            'max_discount'          => 'nullable|numeric|min:0',
            'min_order_amount'      => 'nullable|numeric|min:0',
            'max_uses'              => 'nullable|integer|min:0',
            'max_uses_per_customer' => 'nullable|integer|min:1',
            'is_active'             => 'nullable|boolean',
            'starts_at'             => 'nullable|date',
            'expires_at'            => 'nullable|date|after:starts_at',
        ]);
        $validated['code'] = strtoupper($validated['code']);
        return response()->json(['success' => true, 'data' => Coupon::create($validated)], 201);
    }

    public function updateCoupon(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update($request->validate([
            'name'                  => 'sometimes|required|string|max:255',
            'value'                 => 'sometimes|required|numeric|min:0',
            'max_discount'          => 'nullable|numeric|min:0',
            'min_order_amount'      => 'nullable|numeric|min:0',
            'is_active'             => 'nullable|boolean',
            'expires_at'            => 'nullable|date',
        ]));
        return response()->json(['success' => true, 'data' => $coupon->fresh()]);
    }

    public function destroyCoupon($id)
    {
        Coupon::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Coupon deleted']);
    }

    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code'        => 'required|string',
            'order_total' => 'required|numeric|min:0',
            'customer_id' => 'nullable|exists:tenant_dynamic.ec_customers,id',
        ]);

        $coupon = Coupon::where('code', strtoupper($request->code))->first();

        if (!$coupon || !$coupon->isValid()) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired coupon code'], 422);
        }
        if ($request->filled('customer_id') && !$coupon->canBeUsedByCustomer($request->customer_id)) {
            return response()->json(['success' => false, 'message' => 'Usage limit reached for this customer'], 422);
        }

        $discount = $coupon->calculateDiscount((float) $request->order_total);
        return response()->json([
            'success' => true,
            'data'    => [
                'coupon'           => $coupon,
                'discount_amount'  => $discount,
                'is_free_shipping' => $coupon->type === 'free_shipping',
            ],
        ]);
    }
}
