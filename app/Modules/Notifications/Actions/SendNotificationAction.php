<?php

namespace App\Modules\Notifications\Actions;

use App\Models\Notifications\StoreNotification;
use App\Modules\Notifications\DTOs\NotificationDTO;

class SendNotificationAction
{
    public function execute(NotificationDTO $dto): StoreNotification
    {
        return StoreNotification::create([
            'type' => $dto->type,
            'title' => $dto->title,
            'message' => $dto->message,
            'data' => $dto->data,
            'icon' => $dto->icon,
            'color' => $dto->color,
            'action_url' => $dto->action_url,
            'notifiable_type' => $dto->notifiable_type,
            'notifiable_id' => $dto->notifiable_id,
            'is_broadcast' => $dto->is_broadcast,
            'expires_at' => $dto->expires_at,
        ]);
    }
}
