<?php

namespace App\Modules\POS\Controllers;

use App\Http\Controllers\Controller;
use App\Models\POS\PosProduct;
use App\Models\POS\PosSession;
use App\Models\POS\PosSale;
use App\Models\POS\PosHeldSale;
use App\Models\Ecommerce\Customer;
use App\Modules\POS\Actions\OpenSessionAction;
use App\Modules\POS\Actions\ProcessCheckoutAction;
use App\Modules\POS\Actions\SyncOfflineSalesAction;
use App\Modules\POS\Actions\HoldSaleAction;
use App\Modules\POS\Actions\RecallSaleAction;
use App\Modules\POS\Actions\VerifyStaffPinAction;
use App\Modules\POS\DTOs\CheckoutDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PosController extends Controller
{
    /**
     * Quick login via PIN for POS staff.
     */
    public function pinLogin(Request $request, VerifyStaffPinAction $action): JsonResponse
    {
        $request->validate(['pin' => 'required|string|size:4']);
        
        $user = $action->execute($request->input('pin'));
        
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN'], 401);
        }

        // Ideally issue a new token or return user data for session switching
        return response()->json(['success' => true, 'user' => $user]);
    }

    /**
     * Open a new POS Session.
     */
    public function openSession(Request $request, OpenSessionAction $action): JsonResponse
    {
        $request->validate([
            'opening_balance' => 'required|numeric|min:0',
            'notes'           => 'nullable|string',
        ]);

        $session = $action->execute(
            (float) $request->input('opening_balance'),
            $request->input('notes')
        );

        return response()->json(['success' => true, 'data' => $session]);
    }

    /**
     * Get current active session for the user.
     */
    public function currentSession(): JsonResponse
    {
        $session = PosSession::where('user_id', Auth::id())
            ->where('status', 'open')
            ->with(['branch', 'warehouse'])
            ->first();

        return response()->json(['success' => true, 'data' => $session]);
    }

    /**
     * Process POS Checkout.
     */
    public function checkout(Request $request, ProcessCheckoutAction $action): JsonResponse
    {
        $request->validate([
            'session_id'     => 'required|exists:pos_sessions,id',
            'customer_id'    => 'nullable|exists:customers,id',
            'items'          => 'required|array|min:1',
            'items.*.id'     => 'required|exists:pos_products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price'  => 'required|numeric',
            'payments'       => 'required|array|min:1',
            'payments.*.method' => 'required|in:cash,card,bkash,nagad,points',
            'payments.*.amount' => 'required|numeric',
            'points_count'   => 'nullable|integer|min:0',
            'offline_id'     => 'nullable|string|unique:pos_sales,offline_id',
        ]);

        try {
            $dto = CheckoutDTO::fromRequest($request->all());
            $result = $action->execute($dto);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Bulk sync sales from offline PWA.
     */
    public function sync(Request $request, SyncOfflineSalesAction $action): JsonResponse
    {
        $request->validate(['sales' => 'required|array']);
        
        $results = $action->execute($request->input('sales'));
        
        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * Hold (Park) a current sale.
     */
    public function hold(Request $request, HoldSaleAction $action): JsonResponse
    {
        $request->validate([
            'cart_data' => 'required|array',
            'hold_reference' => 'nullable|string'
        ]);

        $held = $action->execute($request->all());
        
        return response()->json(['success' => true, 'data' => $held]);
    }

    /**
     * Recall a held sale.
     */
    public function recall(int $id, RecallSaleAction $action): JsonResponse
    {
        $cartData = $action->execute($id);
        
        return response()->json(['success' => true, 'data' => $cartData]);
    }

    /**
     * List all held sales for current user/branch.
     */
    public function listHeld(): JsonResponse
    {
        $held = PosHeldSale::where('user_id', Auth::id())->get();
        return response()->json(['success' => true, 'data' => $held]);
    }

    /**
     * Get print-ready receipt data.
     */
    public function receipt(int $id): JsonResponse
    {
        $sale = PosSale::with(['items', 'branch', 'customer', 'payments'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'sale' => $sale,
                'zatca_qr' => $sale->zatca_qr, // B64 TLV for hardware printers
                'store_info' => $sale->branch,
                'timestamp' => $sale->created_at->toIso8601String()
            ]
        ]);
    }

    /**
     * Search products for POS.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $query = $request->input('query');
        $products = PosProduct::where('is_active', true)
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%")
                  ->orWhere('barcode', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get();

        return response()->json(['success' => true, 'data' => $products]);
    }

    /**
     * Search customers by phone or name.
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $query = $request->input('query');
        $customers = Customer::where('name', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->limit(5)
            ->get();

        return response()->json(['success' => true, 'data' => $customers]);
    }
}
