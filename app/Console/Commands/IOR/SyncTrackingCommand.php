<?php

namespace App\Console\Commands\IOR;

use Illuminate\Console\Command;
use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Modules\CrossBorderIOR\Jobs\SyncShipmentOrderJob;
use Illuminate\Support\Facades\Log;

class SyncTrackingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ior:sync-tracking';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync shipment status for all active IOR orders';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Starting IOR Shipment Sync...');

        // Find orders that are:
        // 1. Not delivered or cancelled
        // 2. Have a tracking number
        $orders = IorForeignOrder::whereNotNull('tracking_number')
            ->whereNotIn('order_status', [
                IorForeignOrder::STATUS_DELIVERED,
                IorForeignOrder::STATUS_CANCELLED
            ])
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No active orders with tracking numbers found.');
            return;
        }

        $this->info("Found {$orders->count()} orders to sync. Dispatching jobs...");

        foreach ($orders as $order) {
            SyncShipmentOrderJob::dispatch($order);
            $this->comment("Dispatched job for order: {$order->order_number}");
        }

        $this->info('All jobs dispatched successfully.');
        Log::info("[IOR Sync] Dispatched tracking sync jobs for {$orders->count()} orders.");
    }
}
