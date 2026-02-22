<?php

namespace App\Modules\Finance\Actions;

use App\Models\Finance\Account;
use App\Models\Finance\Transaction;
use App\Models\Finance\Ledger;
use App\Modules\Finance\DTOs\DoubleEntryDTO;
use Illuminate\Support\Facades\DB;

class RecordDoubleEntryAction
{
    public function execute(DoubleEntryDTO $dto): Transaction
    {
        return DB::transaction(function () use ($dto) {
            // 1. Create Transaction Header
            $transaction = Transaction::create([
                'transaction_number' => Transaction::generateNumber(),
                'date' => $dto->date ?? now(),
                'amount' => $dto->amount,
                'description' => $dto->description,
                'reference_type' => $dto->ref_type,
                'reference_id' => $dto->ref_id,
                'created_by' => auth()->id(),
            ]);

            // 2. Record Debit
            $this->addLedgerEntry($transaction->id, $dto->debit_account_id, 'debit', $dto->amount);

            // 3. Record Credit
            $this->addLedgerEntry($transaction->id, $dto->credit_account_id, 'credit', $dto->amount);

            return $transaction;
        });
    }

    protected function addLedgerEntry($transactionId, $accountId, $type, $amount)
    {
        $account = Account::findOrFail($accountId);
        
        if (in_array($account->type, ['asset', 'expense'])) {
            if ($type === 'debit') $account->increment('balance', $amount);
            else $account->decrement('balance', $amount);
        } else {
            if ($type === 'credit') $account->increment('balance', $amount);
            else $account->decrement('balance', $amount);
        }

        Ledger::create([
            'transaction_id' => $transactionId,
            'account_id' => $accountId,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $account->balance,
        ]);
    }
}
