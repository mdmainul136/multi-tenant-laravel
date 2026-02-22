<?php

namespace App\Modules\Ecommerce\Actions;

use App\Models\Ecommerce\Product;
use App\Modules\Ecommerce\DTOs\ProductDTO;

class UpdateProductAction
{
    public function execute(int $id, ProductDTO $dto): Product
    {
        $product = Product::findOrFail($id);
        $product->update($dto->toArray());
        
        return $product->fresh();
    }
}
