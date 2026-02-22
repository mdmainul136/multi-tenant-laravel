<?php

namespace App\Modules\HRM\Actions;

use App\Models\HRM\Staff;
use App\Modules\HRM\DTOs\StaffDTO;

class UpdateStaffAction
{
    public function execute(int $id, StaffDTO $dto): Staff
    {
        $staff = Staff::findOrFail($id);
        $staff->update($dto->toArray());
        
        return $staff->fresh();
    }
}
