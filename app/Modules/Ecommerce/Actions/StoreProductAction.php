<?php

namespace App\Modules\Ecommerce\Actions;

use App\Models\Ecommerce\Product;
use App\Modules\Ecommerce\DTOs\ProductDTO;
use Illuminate\Support\Str;

class StoreProductAction
{
    public function execute(ProductDTO $dto): Product
    {
        $data = $dto->toArray();
        $data['slug'] = Str::slug($dto->name) . '-' . uniqid();
        
        return Product::create($data);
    }
}
