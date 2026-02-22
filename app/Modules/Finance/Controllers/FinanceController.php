<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\Transaction;
use App\Models\Finance\Ledger;
use App\Modules\Finance\Services\FinanceService;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    protected FinanceService $financeService;

    public function __construct(FinanceService $financeService)
    {
        $this->financeService = $financeService;
    }

    /**
     * List all accounts.
     */
    public function getAccounts()
    {
        return response()->json([
            'success' => true,
            'data' => Account::orderBy('code')->get()
        ]);
    }

    /**
     * Record a new transaction (Manual Journal Entry).
     */
    public function recordTransaction(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'debit_account_id' => 'required|exists:tenant_dynamic.ec_finance_accounts,id',
            'credit_account_id' => 'required|exists:tenant_dynamic.ec_finance_accounts,id',
            'description' => 'required|string|max:500',
            'date' => 'nullable|date',
        ]);

        try {
            $transaction = $this->financeService->recordDoubleEntry($validated);
            return response()->json([
                'success' => true,
                'message' => 'Transaction recorded successfully',
                'data' => $transaction->load('ledgers.account')
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Get Ledger for a specific account.
     */
    public function getAccountLedger($accountId)
    {
        $ledgers = Ledger::with('transaction')
            ->where('account_id', $accountId)
            ->latest()
            ->paginate(50);

        return response()->json(['success' => true, 'data' => $ledgers]);
    }

    /**
     * Profit & Loss Report.
     */
    public function getProfitLoss(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        $report = $this->financeService->getIncomeStatement($startDate, $endDate);

        return response()->json(['success' => true, 'data' => $report]);
    }

    /**
     * Store new account (Chart of Accounts).
     */
    public function storeAccount(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:tenant_dynamic.ec_finance_accounts,code',
            'type' => 'required|in:asset,liability,equity,income,expense',
        ]);

        $dto = \App\Modules\Finance\DTOs\AccountDTO::fromRequest($validated);
        $account = app(\App\Modules\Finance\Actions\CreateAccountAction::class)->execute($dto);

        return response()->json(['success' => true, 'data' => $account], 201);
    }
}
