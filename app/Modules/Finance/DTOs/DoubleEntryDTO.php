<?php

namespace App\Modules\Finance\DTOs;

class DoubleEntryDTO
{
    public function __construct(
        public readonly float $amount,
        public readonly int $debit_account_id,
        public readonly int $credit_account_id,
        public readonly string $description,
        public readonly ?string $date = null,
        public readonly ?string $ref_type = null,
        public readonly ?int $ref_id = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            amount: (float) $data['amount'],
            debit_account_id: (int) $data['debit_account_id'],
            credit_account_id: (int) $data['credit_account_id'],
            description: $data['description'],
            date: $data['date'] ?? null,
            ref_type: $data['ref_type'] ?? null,
            ref_id: $data['ref_id'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'debit_account_id' => $this->debit_account_id,
            'credit_account_id' => $this->credit_account_id,
            'description' => $this->description,
            'date' => $this->date,
            'ref_type' => $this->ref_type,
            'ref_id' => $this->ref_id,
        ];
    }
}
