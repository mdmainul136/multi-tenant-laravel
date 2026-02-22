<?php

namespace App\Modules\HRM\Actions;

use App\Models\HRM\Payroll;

class MarkPayrollAsPaidAction
{
    public function execute(int $payrollId, string $method, ?string $note = null): Payroll
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($payrollId, $method, $note) {
            $payroll = Payroll::with('staff')->findOrFail($payrollId);
            
            $payroll->update([
                'status' => 'paid',
                'payment_date' => now(),
                'payment_method' => $method,
                'note' => $note
            ]);

            // Integrate with Finance module
            try {
                app(\App\Modules\Finance\Actions\RecordSalaryExpenseAction::class)->execute($payroll, $method);
            } catch (\Exception $e) {
                // If finance fails, we might still want to proceed with marking as paid,
                // but log the error. Or we could rollback.
                // Given the transaction, we revert the 'paid' status if recording fails.
                throw $e;
            }

            return $payroll;
        });
    }
}
