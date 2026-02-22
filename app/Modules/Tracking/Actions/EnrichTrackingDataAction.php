<?php

namespace App\Modules\Tracking\Actions;

use App\Modules\Tracking\DTOs\TrackingEventDTO;

class EnrichTrackingDataAction
{
    public function execute(TrackingEventDTO $dto, ?array $settings): array
    {
        $data = $dto->payload;

        // Power-Up: Geo-IP Enrichment
        $data['geo'] = [
            'country' => request()->header('CF-IPCountry') ?? 'Unknown',
            'city' => 'Unknown',
        ];

        // Power-Up: Automatic SHA-256 Hashing for PII
        if (isset($data['user_data'])) {
            $fieldsToHash = ['email', 'phone', 'external_id'];
            foreach ($fieldsToHash as $field) {
                if (isset($data['user_data'][$field])) {
                    $key = ($field === 'email') ? 'em' : (($field === 'phone') ? 'ph' : 'external_id');
                    $data['user_data'][$key] = hash('sha256', strtolower(trim($data['user_data'][$field])));
                    unset($data['user_data'][$field]);
                }
            }
        }

        return $data;
    }
}
