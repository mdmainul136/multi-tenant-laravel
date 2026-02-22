<?php

namespace App\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Models\HRM\Payroll;
use App\Modules\HRM\Services\PayrollService;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    protected PayrollService $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    /**
     * List all payrolls.
     */
    public function index(Request $request)
    {
        $query = Payroll::with('staff.department')->latest();

        if ($request->month) {
            $query->where('month', $request->month);
        }

        if ($request->staff_id) {
            $query->where('staff_id', $request->staff_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(20)
        ]);
    }

    /**
     * Generate payroll for a specific month.
     */
    public function generate(Request $request)
    {
        $request->validate(['month' => 'required|date_format:Y-m']);
        
        try {
            $payrolls = $this->payrollService->generateMonthlyPayroll($request->month);
            return response()->json([
                'success' => true,
                'message' => 'Payrolls generated successfully',
                'data' => $payrolls
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Mark payroll as paid.
     */
    public function pay(Request $request, $id)
    {
        $request->validate([
            'payment_method' => 'required|string',
            'note' => 'nullable|string'
        ]);

        try {
            $payroll = $this->payrollService->markAsPaid($id, $request->payment_method, $request->note);
            return response()->json([
                'success' => true,
                'message' => 'Salary marked as paid',
                'data' => $payroll
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get payroll details including items.
     */
    public function show($id)
    {
        $payroll = Payroll::with(['staff.department', 'items'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $payroll]);
    }
}
