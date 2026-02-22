<?php

namespace App\Modules\POS\Actions;

use App\Models\POS\PosHeldSale;

class RecallSaleAction
{
    public function execute(int $heldSaleId): array
    {
        $held = PosHeldSale::findOrFail($heldSaleId);
        $data = $held->cart_data;
        
        $held->delete(); // Remove from held once recalled
        
        return $data;
    }
}
