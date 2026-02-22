<?php

namespace App\Modules\Finance\Actions;

use App\Models\Finance\Account;
use App\Modules\Finance\DTOs\AccountDTO;

class CreateAccountAction
{
    public function execute(AccountDTO $dto): Account
    {
        return Account::create([
            'name' => $dto->name,
            'code' => $dto->code,
            'type' => $dto->type,
        ]);
    }
}
