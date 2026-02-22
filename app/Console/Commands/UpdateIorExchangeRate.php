<?php

namespace App\Console\Commands;

use App\Models\CrossBorderIOR\IorSetting;
use App\Modules\CrossBorderIOR\Services\ExchangeRateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateIorExchangeRate extends Command
{
    protected $signature   = 'ior:update-exchange-rate {--force : Force-refresh bypassing cache}';
    protected $description = 'Fetch the latest USD→BDT exchange rate and persist it to ior_settings. Run daily via scheduler.';

    public function handle(ExchangeRateService $fx): int
    {
        $this->info('[IOR] Fetching USD→BDT exchange rate...');

        try {
            $force = $this->option('force');

            if ($force) {
                $fx->clearCache();
            }

            $rate = $fx->getUsdToBdt(forceRefresh: true);

            IorSetting::set('last_exchange_rate', (string) $rate, 'pricing');
            IorSetting::set('exchange_rate_updated_at', now()->toISOString(), 'pricing');

            $this->info("[IOR] Exchange rate updated: 1 USD = {$rate} BDT");
            Log::info("[IOR Scheduler] Exchange rate updated to $rate BDT/USD");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('[IOR] Failed to update exchange rate: ' . $e->getMessage());
            Log::error('[IOR Scheduler] Exchange rate update failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}



