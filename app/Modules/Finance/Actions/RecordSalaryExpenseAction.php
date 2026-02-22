<?php

namespace App\Modules\Finance\Actions;

use App\Models\Finance\Account;
use App\Models\Finance\Transaction;
use App\Modules\Finance\DTOs\DoubleEntryDTO;
use App\Models\HRM\Payroll;
use Illuminate\Support\Facades\DB;

class RecordSalaryExpenseAction
{
    public function __construct(
        private RecordDoubleEntryAction $doubleEntry
    ) {}

    /**
     * Records a payroll payment in the general ledger.
     * Debits "Salary Expense" and Credits "Cash/Bank".
     */
    public function execute(Payroll $payroll, string $method): Transaction
    {
        return DB::transaction(function () use ($payroll, $method) {
            // Find or fallback accounts
            $expenseAccount = Account::where('name', 'Salary Expense')
                ->orWhere('name', 'Payroll Expense')
                ->first();
                
            $paymentAccount = Account::where('name', 'Cash')
                ->orWhere('name', 'Bank')
                ->first();

            if (!$expenseAccount || !$paymentAccount) {
                // Fail-safe or create if missing? Usually we want to fail to alert admin
                throw new \Exception("Accounting accounts for Payroll (Salary Expense/Cash) not found. Please setup Chart of Accounts.");
            }

            $dto = new DoubleEntryDTO(
                amount: $payroll->net_salary,
                debit_account_id: $expenseAccount->id,
                credit_account_id: $paymentAccount->id,
                description: "Salary Payment for {$payroll->staff->name} - {$payroll->month} (Method: {$method})",
                date: now()->toDateString(),
                ref_type: 'hrm_payroll',
                ref_id: $payroll->id
            );

            return $this->doubleEntry->execute($dto);
        });
    }
}
