<?php

namespace App\Modules\Finance\Services;

use App\Models\Finance\Account;
use App\Models\Finance\Transaction;
use App\Models\Finance\Ledger;
use App\Modules\Finance\DTOs\DoubleEntryDTO;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    /**
     * Record a simple journal entry (Double Entry).
     */
    public function recordDoubleEntry(array $params): Transaction
    {
        $dto = DoubleEntryDTO::fromRequest($params);
        return app(\App\Modules\Finance\Actions\RecordDoubleEntryAction::class)->execute($dto);
    }


    /**
     * Record automated income (e.g., from Sales).
     */
    public function recordIncome(float $amount, string $description, string $refType = null, $refId = null)
    {
        $cashAccount = Account::where('code', '1001')->first() ?? Account::where('type', 'asset')->first();
        $salesAccount = Account::where('code', '4001')->first() ?? Account::where('type', 'income')->first();

        if ($cashAccount && $salesAccount) {
            $this->recordDoubleEntry([
                'amount' => $amount,
                'debit_account_id' => $cashAccount->id,
                'credit_account_id' => $salesAccount->id,
                'description' => $description,
                'ref_type' => $refType,
                'ref_id' => $refId,
            ]);
        }
    }

    /**
     * Record automated expense (e.g., from Payroll).
     */
    public function recordExpense(float $amount, string $description, string $refType = null, $refId = null)
    {
        $cashAccount = Account::where('code', '1001')->first() ?? Account::where('type', 'asset')->first();
        $expenseAccount = Account::where('code', '5001')->first() ?? Account::where('type', 'expense')->first();

        if ($cashAccount && $expenseAccount) {
            $this->recordDoubleEntry([
                'amount' => $amount,
                'debit_account_id' => $expenseAccount->id,
                'credit_account_id' => $cashAccount->id,
                'description' => $description,
                'ref_type' => $refType,
                'ref_id' => $refId,
            ]);
        }
    }

    /**
     * Get Income Statement Data.
     */
    public function getIncomeStatement($startDate, $endDate)
    {
        return app(\App\Modules\Finance\Actions\GetIncomeStatementAction::class)->execute($startDate, $endDate);
    }
}
