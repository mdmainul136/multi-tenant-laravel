<?php

namespace App\Modules\CRM\Actions;

use App\Models\CRM\Customer;
use App\Modules\CRM\DTOs\CustomerDTO;

class StoreCustomerAction
{
    public function execute(CustomerDTO $dto): Customer
    {
        return Customer::create($dto->toArray());
    }
}
