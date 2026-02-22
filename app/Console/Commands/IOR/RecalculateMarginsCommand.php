<?php

namespace App\Console\Commands\IOR;

use App\Modules\CrossBorderIOR\Services\WarehouseMarginCalculator;
use Illuminate\Console\Command;

class RecalculateMarginsCommand extends Command
{
    protected $signature = 'ior:recalculate-margins {--ids= : Comma-separated product IDs}';
    protected $description = 'Recalculate local BDT prices based on current USD costs and margin settings';

    public function handle(WarehouseMarginCalculator $service): int
    {
        $idsRaw = $this->option('ids');
        $ids = $idsRaw ? array_map('intval', explode(',', $idsRaw)) : null;

        $this->info('🧮 Recalculating IOR Margins...');
        
        $result = $service->recalculateAll($ids);

        $this->info("✅ Done. Updated {$result['updated']}/{$result['total']} products.");

        return self::SUCCESS;
    }
}
