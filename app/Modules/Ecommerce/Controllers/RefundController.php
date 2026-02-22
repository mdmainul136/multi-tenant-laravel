<?php

namespace App\Modules\Ecommerce\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ecommerce\Refund;
use App\Modules\Ecommerce\Services\RefundService;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    protected RefundService $refundService;

    public function __construct(RefundService $refundService)
    {
        $this->refundService = $refundService;
    }

    /**
     * List all refund requests.
     */
    public function index(Request $request)
    {
        $query = Refund::with(['order', 'customer'])->latest();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(15)
        ]);
    }

    /**
     * Create a new refund request (e.g. from Order Cancellation).
     */
    public function store(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:ec_orders,id',
            'customer_id' => 'required|exists:ec_customers,id',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
            'refund_method' => 'required|in:wallet,original_method',
        ]);

        $refund = $this->refundService->requestRefund($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Refund request submitted successfully',
            'data' => $refund
        ], 201);
    }

    /**
     * Approve and process a refund.
     */
    public function approve(Request $request, $id)
    {
        try {
            $refund = $this->refundService->approveRefund($id, $request->admin_note);

            return response()->json([
                'success' => true,
                'message' => 'Refund approved and processed',
                'data' => $refund
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Reject a refund request.
     */
    public function reject(Request $request, $id)
    {
        $request->validate(['admin_note' => 'required|string']);

        $refund = $this->refundService->rejectRefund($id, $request->admin_note);

        return response()->json([
            'success' => true,
            'message' => 'Refund request rejected',
            'data' => $refund
        ]);
    }
}
