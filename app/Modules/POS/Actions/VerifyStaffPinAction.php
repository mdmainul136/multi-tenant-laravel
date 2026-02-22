<?php

namespace App\Modules\POS\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class VerifyStaffPinAction
{
    public function execute(string $pin): ?User
    {
        // For production, we would use a more secure way to store PINs (hashed)
        // For this implementation, we assume pin_code is stored as a string
        $user = User::where('pin_code', $pin)
            ->where('status', 'active')
            ->first();

        return $user;
    }
}
