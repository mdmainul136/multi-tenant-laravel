<?php

namespace App\Modules\Marketing\Actions;

use App\Models\Marketing\Campaign;
use App\Models\Marketing\CampaignLog;
use App\Models\Marketing\CampaignVariant;
use App\Modules\Marketing\Services\AudienceService;
use Illuminate\Support\Facades\Log;

class ExecuteCampaignAction
{
    public function __construct(
        private AudienceService $audienceService
    ) {}

    public function execute(Campaign $campaign): void
    {
        $campaign->update(['status' => 'sending', 'started_at' => now()]);

        try {
            $customers = $this->audienceService->getAudienceCustomers($campaign->audience)->get();
            $variants  = $campaign->variants;

            foreach ($customers as $customer) {
                $variant = $this->pickVariant($campaign, $variants);
                $this->sendToRecipient($campaign, $variant, $customer);
            }

            $campaign->update(['status' => 'completed', 'completed_at' => now()]);
        } catch (\Exception $e) {
            Log::error("[Marketing] Campaign {$campaign->id} failed: " . $e->getMessage());
            $campaign->update(['status' => 'failed']);
        }
    }

    private function pickVariant(Campaign $campaign, $variants)
    {
        if (!$campaign->is_ab_test || $variants->count() <= 1) {
            return $variants->first();
        }
        return $variants->random();
    }

    private function sendToRecipient(Campaign $campaign, ?CampaignVariant $variant, $customer): void
    {
        $recipient = ($campaign->channel === 'email') ? $customer->email : $customer->phone;
        if (!$recipient) return;

        $log = CampaignLog::create([
            'campaign_id' => $campaign->id,
            'variant_id'  => $variant?->id,
            'customer_id' => $customer->id,
            'recipient'   => $recipient,
            'status'      => 'pending',
        ]);

        try {
            // Integration logic here (Email/SMS/WA)
            $log->update(['status' => 'sent']);
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }
    }
}
