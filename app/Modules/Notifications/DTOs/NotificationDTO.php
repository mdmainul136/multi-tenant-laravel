<?php

namespace App\Modules\Notifications\DTOs;

class NotificationDTO
{
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly string $message,
        public readonly ?array $data = null,
        public readonly ?string $icon = null,
        public readonly ?string $color = null,
        public readonly ?string $action_url = null,
        public readonly ?string $notifiable_type = null,
        public readonly ?int $notifiable_id = null,
        public readonly bool $is_broadcast = false,
        public readonly ?string $expires_at = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            type: $data['type'],
            title: $data['title'],
            message: $data['message'],
            data: $data['data'] ?? null,
            icon: $data['icon'] ?? null,
            color: $data['color'] ?? null,
            action_url: $data['action_url'] ?? null,
            notifiable_type: $data['notifiable_type'] ?? null,
            notifiable_id: isset($data['notifiable_id']) ? (int) $data['notifiable_id'] : null,
            is_broadcast: (bool) ($data['is_broadcast'] ?? false),
            expires_at: $data['expires_at'] ?? null
        );
    }
}
