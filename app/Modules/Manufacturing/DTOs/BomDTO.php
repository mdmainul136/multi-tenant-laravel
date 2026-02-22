<?php

namespace App\Modules\Manufacturing\DTOs;

class BomDTO
{
    public function __construct(
        public readonly int $finished_product_id,
        public readonly string $name,
        public readonly array $items
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            finished_product_id: (int) $data['finished_product_id'],
            name: $data['name'],
            items: $data['items']
        );
    }
}
