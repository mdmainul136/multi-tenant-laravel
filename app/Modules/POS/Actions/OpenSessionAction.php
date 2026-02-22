<?php

namespace App\Modules\POS\Actions;

use App\Models\POS\PosSession;
use Illuminate\Support\Facades\Auth;

class OpenSessionAction
{
    public function execute(float $openingBalance, ?string $notes = null): PosSession
    {
        // Check for existing open session for this user
        $existing = PosSession::where('user_id', Auth::id())
            ->where('status', 'open')
            ->first();

        if ($existing) {
            return $existing;
        }

        return PosSession::create([
            'user_id'         => Auth::id(),
            'opening_balance' => $openingBalance,
            'status'          => 'open',
            'opened_at'       => now(),
            'notes'           => $notes,
        ]);
    }
}
