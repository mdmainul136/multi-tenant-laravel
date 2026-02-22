<?php

namespace App\Modules\HRM\Actions;

use App\Models\HRM\Staff;
use App\Models\HRM\Payroll;
use App\Models\HRM\PayrollItem;
use App\Models\HRM\Attendance;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyPayrollAction
{
    public function execute(string $month): array
    {
        $staffMembers = Staff::where('status', 'active')->get();
        $generated = [];

        foreach ($staffMembers as $staff) {
            $generated[] = $this->generateForStaff($staff, $month);
        }

        return $generated;
    }

    protected function generateForStaff(Staff $staff, string $month): Payroll
    {
        return DB::transaction(function () use ($staff, $month) {
            // Check if already exists
            $existing = Payroll::where('staff_id', $staff->id)->where('month', $month)->first();
            if ($existing) return $existing;

            $carbonMonth = \Carbon\Carbon::parse($month . '-01');
            $daysInMonth = $carbonMonth->daysInMonth;
            
            $basicSalary = $staff->salary;
            $allowances  = 0;
            $deductions = 0;
            $netSalary   = 0;

            if ($staff->salary_type === 'monthly') {
                // Monthly Salary Logic
                // 1. Calculate Unpaid Absences (Absent without approved leave)
                // We assume Sundays are off (simplified) or we just count literal 'absent' marks
                $absentDays = Attendance::where('staff_id', $staff->id)
                    ->where('date', 'like', "{$month}%")
                    ->where('status', 'absent')
                    ->count();

                // 2. Subtract Approved Leave if it's 'unpaid'
                $unpaidLeaveDays = \App\Models\HRM\LeaveRequest::approved()
                    ->where('staff_id', $staff->id)
                    ->where('type', 'unpaid')
                    ->where(function($q) use ($month) {
                        $q->where('from_date', 'like', "{$month}%")
                          ->orWhere('to_date', 'like', "{$month}%");
                    })
                    ->sum('days');

                $totalUnpaidDays = $absentDays + $unpaidLeaveDays;
                $perDaySalary    = $basicSalary / $daysInMonth;
                $deductions      = round($totalUnpaidDays * $perDaySalary, 2);
                $netSalary       = $basicSalary - $deductions;

            } elseif ($staff->salary_type === 'hourly') {
                // Hourly Salary Logic: Sum of worked hours in that month
                $totalHours = Attendance::where('staff_id', $staff->id)
                    ->where('date', 'like', "{$month}%")
                    ->sum('hours_worked');
                
                $basicSalary = 0; // For hourly, we consider the "basic" to be 0 and everything depends on hours
                $netSalary   = round($totalHours * $staff->salary, 2); // Here staff->salary is the hourly rate
            } else {
                 $netSalary = $basicSalary;
            }

            $payroll = Payroll::create([
                'staff_id' => $staff->id,
                'month' => $month,
                'basic_salary' => $staff->salary,
                'total_allowance' => $allowances,
                'total_deduction' => $deductions,
                'net_salary' => $netSalary,
                'status' => 'generated'
            ]);

            if ($deductions > 0) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'title' => 'Unpaid Absence/Leave (' . ($absentDays + $unpaidLeaveDays) . ' days)',
                    'type' => 'deduction',
                    'amount' => $deductions
                ]);
            }

            if ($staff->salary_type === 'hourly') {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'title' => 'Hourly Wage (' . $totalHours . ' hours)',
                    'type' => 'allowance',
                    'amount' => $netSalary
                ]);
            }

            return $payroll;
        });
    }
}
