<?php

namespace App\Console\Commands;

use App\Services\TenantDatabaseAnalyticsService;
use Illuminate\Console\Command;

class CollectDatabaseStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:collect-db-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect database usage statistics for all active tenants';

    public function __construct(
        protected TenantDatabaseAnalyticsService $analyticsService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting database stats collection...');

        $startTime = microtime(true);
        $processed = $this->analyticsService->collectAllStats();
        $elapsed = round(microtime(true) - $startTime, 2);

        $this->info("Collected stats for {$processed} tenants in {$elapsed}s.");

        return Command::SUCCESS;
    }
}
