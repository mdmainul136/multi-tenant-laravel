<?php

namespace App\Modules\Marketing\Actions;

use App\Models\Marketing\Campaign;
use App\Modules\Marketing\DTOs\CampaignDTO;
use Illuminate\Support\Facades\DB;

class StoreCampaignAction
{
    public function execute(CampaignDTO $dto): Campaign
    {
        return DB::transaction(function () use ($dto) {
            $campaign = Campaign::create([
                'name'           => $dto->name,
                'audience_id'    => $dto->audience_id,
                'channel'        => $dto->channel,
                'is_ab_test'     => $dto->is_ab_test,
                'scheduled_at'   => $dto->scheduled_at,
                'settings'       => $dto->settings,
                'status'         => $dto->scheduled_at ? 'scheduled' : 'draft',
            ]);

            foreach ($dto->variants as $v) {
                $campaign->variants()->create([
                    'template_id' => $v['template_id'],
                    'name'        => $v['name'] ?? 'Default',
                    'ratio'       => $v['ratio'] ?? 100,
                ]);
            }

            return $campaign;
        });
    }
}
