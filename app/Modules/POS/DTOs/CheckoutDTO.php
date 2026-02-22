<?php

namespace App\Modules\POS\DTOs;

class CheckoutDTO
{
    public function __construct(
        public readonly int $session_id,
        public readonly ?int $customer_id,
        public readonly array $items,
        public readonly array $payments,
        public readonly ?int $points_count = 0,
        public readonly ?string $notes = null,
        public readonly ?int $branch_id = null,
        public readonly ?int $warehouse_id = null,
        public readonly ?string $offline_id = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            session_id: $data['session_id'],
            customer_id: $data['customer_id'] ?? null,
            items: $data['items'],
            payments: $data['payments'],
            points_count: $data['points_count'] ?? 0,
            notes: $data['notes'] ?? null,
            branch_id: $data['branch_id'] ?? null,
            warehouse_id: $data['warehouse_id'] ?? null,
            offline_id: $data['offline_id'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'session_id' => $this->session_id,
            'customer_id' => $this->customer_id,
            'items' => $this->items,
            'payments' => $this->payments,
            'points_count' => $this->points_count,
            'notes' => $this->notes,
            'branch_id' => $this->branch_id,
            'warehouse_id' => $this->warehouse_id,
            'offline_id' => $this->offline_id,
        ];
    }
}
