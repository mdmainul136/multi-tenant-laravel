<?php

namespace App\Modules\POS\Actions;

use App\Modules\POS\DTOs\CheckoutDTO;
use Illuminate\Support\Facades\Log;

class SyncOfflineSalesAction
{
    public function __construct(
        private ProcessCheckoutAction $checkoutAction
    ) {}

    /**
     * Sync multiple sales from offline PWA storage.
     * 
     * @param array $sales List of checkout requests.
     */
    public function execute(array $sales): array
    {
        $results = [
            'synced' => 0,
            'failed' => 0,
            'duplicates' => 0,
            'errors' => []
        ];

        foreach ($sales as $saleData) {
            try {
                $dto = CheckoutDTO::fromRequest($saleData);
                $result = $this->checkoutAction->execute($dto);

                if (isset($result['duplicate'])) {
                    $results['duplicates']++;
                } else {
                    $results['synced']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'offline_id' => $saleData['offline_id'] ?? 'unknown',
                    'message' => $e->getMessage()
                ];
                Log::error("[POS Sync] Failed to sync sale: " . $e->getMessage());
            }
        }

        return $results;
    }
}
