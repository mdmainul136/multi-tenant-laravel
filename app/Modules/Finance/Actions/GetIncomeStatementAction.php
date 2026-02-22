<?php

namespace App\Modules\Finance\Actions;

use App\Models\Finance\Account;

class GetIncomeStatementAction
{
    public function execute($startDate, $endDate): array
    {
        $income = Account::where('type', 'income')->withSum(['ledgers' => function($q) use ($startDate, $endDate) {
            $q->whereBetween('created_at', [$startDate, $endDate]);
        }], 'amount')->get();

        $expenses = Account::where('type', 'expense')->withSum(['ledgers' => function($q) use ($startDate, $endDate) {
            $q->whereBetween('created_at', [$startDate, $endDate]);
        }], 'amount')->get();

        return [
            'income' => $income,
            'expenses' => $expenses,
            'total_income' => $income->sum('ledgers_sum_amount'),
            'total_expense' => $expenses->sum('ledgers_sum_amount'),
            'net_profit' => $income->sum('ledgers_sum_amount') - $expenses->sum('ledgers_sum_amount'),
        ];
    }
}
