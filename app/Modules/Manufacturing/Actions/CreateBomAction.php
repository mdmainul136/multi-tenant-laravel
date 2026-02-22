<?php

namespace App\Modules\Manufacturing\Actions;

use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Modules\Manufacturing\DTOs\BomDTO;
use Illuminate\Support\Facades\DB;

class CreateBomAction
{
    public function execute(BomDTO $dto): Bom
    {
        return DB::transaction(function () use ($dto) {
            $bom = Bom::create([
                'finished_product_id' => $dto->finished_product_id,
                'name' => $dto->name
            ]);

            foreach ($dto->items as $item) {
                BomItem::create([
                    'bom_id' => $bom->id,
                    'raw_material_id' => $item['product_id'],
                    'quantity' => $item['quantity']
                ]);
            }

            return $bom;
        });
    }
}
