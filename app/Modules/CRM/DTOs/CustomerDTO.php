<?php

namespace App\Modules\CRM\DTOs;

class CustomerDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $address = null,
        public readonly ?string $city = null,
        public readonly ?string $country = null,
        public readonly ?string $notes = null,
        public readonly bool $is_active = true
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            address: $data['address'] ?? null,
            city: $data['city'] ?? null,
            country: $data['country'] ?? null,
            notes: $data['notes'] ?? null,
            is_active: (bool) ($data['is_active'] ?? true)
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
        ];
    }
}
