<?php

namespace App\Modules\Ecommerce\DTOs;

class ProductDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $sku,
        public readonly float $price,
        public readonly int $stock_quantity,
        public readonly ?string $category = null,
        public readonly ?string $description = null,
        public readonly string $product_type = 'local',
        public readonly float $weight = 0.0,
        public readonly float $cost = 0.0,
        public readonly bool $is_active = true
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            sku: $data['sku'],
            price: (float) $data['price'],
            stock_quantity: (int) $data['stock_quantity'],
            category: $data['category'] ?? null,
            description: $data['description'] ?? null,
            product_type: $data['product_type'] ?? 'local',
            weight: (float) ($data['weight'] ?? 0.0),
            cost: (float) ($data['cost'] ?? 0.0),
            is_active: (bool) ($data['is_active'] ?? true)
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'sku' => $this->sku,
            'price' => $this->price,
            'stock_quantity' => $this->stock_quantity,
            'category' => $this->category,
            'description' => $this->description,
            'product_type' => $this->product_type,
            'weight' => $this->weight,
            'cost' => $this->cost,
            'is_active' => $this->is_active,
        ];
    }
}
