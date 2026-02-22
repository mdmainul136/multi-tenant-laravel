<?php

namespace App\Modules\CRM\Actions;

use App\Models\CRM\Customer;
use App\Modules\CRM\DTOs\CustomerDTO;

class UpdateCustomerAction
{
    public function execute(int $id, CustomerDTO $dto): Customer
    {
        $customer = Customer::findOrFail($id);
        $customer->update($dto->toArray());
        
        return $customer->fresh();
    }
}
