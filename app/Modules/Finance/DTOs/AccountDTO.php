<?php

namespace App\Modules\Finance\DTOs;

class AccountDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $code,
        public readonly string $type
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            code: $data['code'],
            type: $data['type']
        );
    }
}
