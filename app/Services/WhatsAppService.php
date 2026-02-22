<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $apiUrl;
    protected $apiKey;
    protected $fromNumber;

    public function __construct()
    {
        $this->apiUrl = config('services.whatsapp.url');
        $this->apiKey = config('services.whatsapp.key');
        $this->fromNumber = config('services.whatsapp.from');
    }

    /**
     * Send a template message (e.g., Order Confirmation).
     */
    public function sendTemplateMessage($to, $templateName, $parameters = [])
    {
        if (app()->environment('local') || config('services.whatsapp.mock')) {
            Log::info("MOCK WHATSAPP: Sending template '{$templateName}' to {$to}", $parameters);
            return true;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->post("{$this->apiUrl}/messages", [
                    'from' => $this->fromNumber,
                    'to' => $to,
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => ['code' => 'en'],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => $this->formatParameters($parameters)
                            ]
                        ]
                    ]
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("WhatsApp Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Shortcut for order confirmation.
     */
    public function sendOrderConfirmation($to, $orderNumber, $amount)
    {
        return $this->sendTemplateMessage($to, 'order_confirmation', [
            $orderNumber,
            $amount
        ]);
    }

    protected function formatParameters($params)
    {
        return array_map(function($p) {
            return ['type' => 'text', 'text' => (string)$p];
        }, $params);
    }
}
