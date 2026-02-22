<?php

namespace App\Modules\Marketing\DTOs;

class CampaignDTO
{
    public function __construct(
        public readonly string $name,
        public readonly int $audience_id,
        public readonly string $channel,
        public readonly bool $is_ab_test = false,
        public readonly array $variants = [],
        public readonly ?string $scheduled_at = null,
        public readonly ?array $settings = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            audience_id: $data['audience_id'],
            channel: $data['channel'],
            is_ab_test: (bool) ($data['is_ab_test'] ?? false),
            variants: $data['variants'] ?? [],
            scheduled_at: $data['scheduled_at'] ?? null,
            settings: $data['settings'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'audience_id' => $this->audience_id,
            'channel' => $this->channel,
            'is_ab_test' => $this->is_ab_test,
            'variants' => $this->variants,
            'scheduled_at' => $this->scheduled_at,
            'settings' => $this->settings,
        ];
    }
}
