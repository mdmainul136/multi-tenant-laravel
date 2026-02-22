<?php

namespace App\Modules\POS\Actions;

use App\Models\POS\PosHeldSale;
use Illuminate\Support\Facades\Auth;

class HoldSaleAction
{
    public function execute(array $data): PosHeldSale
    {
        return PosHeldSale::create([
            'user_id'        => Auth::id(),
            'branch_id'      => Auth::user()->branch_id,
            'customer_name'  => $data['customer_name'] ?? null,
            'cart_data'      => $data['cart_data'],
            'notes'          => $data['notes'] ?? null,
            'hold_reference' => $data['hold_reference'] ?? ('Hold ' . now()->format('H:i')),
        ]);
    }
}
