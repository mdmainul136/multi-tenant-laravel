<?php

namespace App\Modules\Notifications\Actions;

use App\Models\Notifications\NotificationTemplate;
use App\Models\Notifications\StoreNotification;
use App\Modules\Notifications\DTOs\NotificationDTO;

class SendTemplateNotificationAction
{
    public function execute(string $templateKey, array $variables = [], array $options = []): StoreNotification
    {
        $template = NotificationTemplate::where('key', $templateKey)->active()->firstOrFail();
        $rendered = $template->render($variables);

        $dto = new NotificationDTO(
            type: $template->key,
            title: $rendered['title'],
            message: $rendered['message'],
            icon: $template->icon,
            color: $template->color,
            action_url: $options['action_url'] ?? null,
            notifiable_type: $options['notifiable_type'] ?? null,
            notifiable_id: $options['notifiable_id'] ?? null,
            is_broadcast: !isset($options['notifiable_id'])
        );

        return app(SendNotificationAction::class)->execute($dto);
    }
}
