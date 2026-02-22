<?php

namespace App\Modules\Tracking\DTOs;

class TrackingEventDTO
{
    public function __construct(
        public readonly string $eventName,
        public readonly ?string $eventId = null,
        public readonly array $payload = [],
        public readonly ?bool $consent = null,
        public readonly ?string $sourceIp = null,
        public readonly ?string $userAgent = null
    ) {}

    public static function fromRequest(array $data, ?string $ip = null, ?string $ua = null): self
    {
        return new self(
            eventName: $data['event_name'] ?? 'unknown',
            eventId: $data['event_id'] ?? null,
            payload: $data,
            consent: isset($data['consent']) ? (bool) $data['consent'] : null,
            sourceIp: $ip,
            userAgent: $ua
        );
    }
}
