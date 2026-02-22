<?php

namespace App\Modules\CrossBorderIOR\DTOs;

class IorPricingDTO
{
    public function __construct(
        public readonly float $usdPrice,
        public readonly float $weightKg = 0.5,
        public readonly string $productTitle = '',
        public readonly string $shippingMethod = 'air',
        public readonly ?float $customRate = null,
        public readonly ?float $marginRate = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            usdPrice: (float) $data['usd_price'],
            weightKg: (float) ($data['weight_kg'] ?? 0.5),
            productTitle: $data['product_title'] ?? '',
            shippingMethod: $data['shipping_method'] ?? 'air',
            customRate: isset($data['custom_rate']) ? (float) $data['custom_rate'] : null,
            marginRate: isset($data['margin_rate']) ? (float) $data['margin_rate'] : null
        );
    }
}
