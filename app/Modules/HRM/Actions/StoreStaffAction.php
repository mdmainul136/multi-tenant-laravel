<?php

namespace App\Modules\HRM\Actions;

use App\Models\HRM\Staff;
use App\Modules\HRM\DTOs\StaffDTO;

class StoreStaffAction
{
    public function execute(StaffDTO $dto): Staff
    {
        $data = $dto->toArray();
        $data['employee_id'] = Staff::generateEmployeeId();
        
        return Staff::create($data);
    }
}
