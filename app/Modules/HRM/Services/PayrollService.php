<?php

namespace App\Modules\HRM\Services;

use App\Models\HRM\Staff;
use App\Models\HRM\Payroll;
use App\Models\HRM\PayrollItem;
use App\Models\HRM\Attendance;
use App\Models\HRM\LeaveRequest;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    /**
     * Generate payroll for a specific month for all active staff.
     */
    public function generateMonthlyPayroll(string $month)
    {
        return app(\App\Modules\HRM\Actions\GenerateMonthlyPayrollAction::class)->execute($month);
    }

    /**
     * Pay the payroll.
     */
    public function markAsPaid(int $payrollId, string $method, string $note = null): Payroll
    {
        return app(\App\Modules\HRM\Actions\MarkPayrollAsPaidAction::class)->execute($payrollId, $method, $note);
    }
}
