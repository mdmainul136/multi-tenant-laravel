<?php

namespace App\Modules\Inventory\DTOs;

class StockAdjustmentDTO
{
    public function __construct(
        public readonly int $product_id,
        public readonly int $warehouse_id,
        public readonly int $change,
        public readonly string $type,
        public readonly ?string $note = null,
        public readonly ?string $ref_type = null,
        public readonly ?int $ref_id = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            product_id: (int) $data['product_id'],
            warehouse_id: (int) $data['warehouse_id'],
            change: (int) $data['change'],
            type: $data['type'],
            note: $data['note'] ?? null,
            ref_type: $data['ref_type'] ?? null,
            ref_id: $data['ref_id'] ?? null
        );
    }
}
