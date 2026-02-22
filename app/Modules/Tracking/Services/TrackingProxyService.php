<?php

namespace App\Modules\Tracking\Services;

use App\Models\Tracking\TrackingContainer;
use App\Models\Tracking\TrackingEventLog;
use Illuminate\Support\Facades\Http;

class TrackingProxyService
{
    /**
     * Enrich data and forward to downstream APIs (e.g. Facebook CAPI).
     */
    public function processEvent(TrackingContainer $container, array $data)
    {
        $dto = \App\Modules\Tracking\DTOs\TrackingEventDTO::fromRequest(
            $data, 
            request()->ip(), 
            request()->userAgent()
        );

        return app(\App\Modules\Tracking\Actions\ProcessTrackingEventAction::class)->execute($container, $dto);
    }
}
