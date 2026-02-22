<?php

namespace App\Modules\WhatsApp\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    protected $apiKey;
    protected $fromNumber;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.whatsapp.key');
        $this->fromNumber = config('services.whatsapp.from');
        $this->baseUrl = config('services.whatsapp.url', 'https://api.whatsapp.com/v1');
    }

    /**
     * Send a template message to a customer.
     */
    public function sendTemplateNotification(string $to, string $template, array $params)
    {
        // Mocking the actual API call for now
        Log::info("WhatsApp Notification Queued: To: $to, Template: $template, Params: " . json_encode($params));

        if (!$this->apiKey) {
            return [
                'success' => true, 
                'message' => 'Notification logged (Staging/Mock Mode)',
                'mock' => true
            ];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->post("{$this->baseUrl}/messages", [
                    'to' => $to,
                    'type' => 'template',
                    'template' => [
                        'name' => $template,
                        'language' => ['code' => 'en_US'],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => array_map(fn($p) => ['type' => 'text', 'text' => $p], $params)
                            ]
                        ]
                    ]
                ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error("WhatsApp API Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Convenience method for order confirmation.
     */
    public function sendOrderConfirmation(string $to, string $orderNumber, string $total)
    {
        return $this->sendTemplateNotification($to, 'order_placed', [
            $orderNumber,
            $total
        ]);
    }
}
