<?php

namespace App\Modules\Ecommerce\DTOs;

class OrderDTO
{
    public function __construct(
        public readonly ?int $customer_id,
        public readonly array $items,
        public readonly string $payment_method = 'cod',
        public readonly string $shipping_method = 'air',
        public readonly ?string $shipping_full_name = null,
        public readonly ?string $shipping_phone = null,
        public readonly ?string $shipping_address = null,
        public readonly ?string $shipping_city = null,
        public readonly ?string $shipping_postal_code = null,
        public readonly ?string $shipping_country = 'Bangladesh',
        public readonly ?string $guest_email = null,
        public readonly ?string $notes = null,
        public readonly ?float $subtotal = null,
        public readonly ?float $shipping_cost = null,
        public readonly ?float $tax_amount = null,
        public readonly ?float $discount_amount = null,
        public readonly ?float $total_amount = null,
        public readonly ?string $discount_code = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            customer_id: isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            items: $data['items'] ?? [],
            payment_method: $data['payment_method'] ?? 'cod',
            shipping_method: $data['shipping_method'] ?? 'air',
            shipping_full_name: $data['shipping_full_name'] ?? $data['shippingFullName'] ?? null,
            shipping_phone: $data['shipping_phone'] ?? $data['shippingPhone'] ?? null,
            shipping_address: $data['shipping_address'] ?? $data['shippingAddress'] ?? null,
            shipping_city: $data['shipping_city'] ?? $data['shippingCity'] ?? null,
            shipping_postal_code: $data['shipping_postal_code'] ?? $data['shippingPostalCode'] ?? null,
            shipping_country: $data['shipping_country'] ?? $data['shippingCountry'] ?? 'Bangladesh',
            guest_email: $data['guest_email'] ?? $data['guestEmail'] ?? null,
            notes: $data['notes'] ?? null,
            subtotal: isset($data['subtotal']) ? (float) $data['subtotal'] : null,
            shipping_cost: isset($data['shipping_cost']) ? (float) $data['shipping_cost'] : (isset($data['shipping']) ? (float) $data['shipping'] : null),
            tax_amount: isset($data['tax_amount']) ? (float) $data['tax_amount'] : (isset($data['tax']) ? (float) $data['tax'] : null),
            discount_amount: isset($data['discount_amount']) ? (float) $data['discount_amount'] : (isset($data['discount']) ? (float) $data['discount'] : null),
            total_amount: isset($data['total_amount']) ? (float) $data['total_amount'] : (isset($data['total']) ? (float) $data['total'] : null),
            discount_code: $data['discount_code'] ?? $data['discountCode'] ?? null,
        );
    }
}
