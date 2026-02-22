<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 🚀 GLOBAL QUEUE PROTECTION:
        // Automatically switch tenant DB context for every queued job that is TenantAware
        \Illuminate\Support\Facades\Queue::before(function (\Illuminate\Queue\Events\JobProcessing $event) {
            $job = $event->job->getRawBody();
            $data = json_decode($job, true);
            
            // Re-instantiate the job object to check for trait or manually check payload
            // In Laravel 11, the job instance might be available via $event->job->resolveName()
            
            if (isset($data['data']['command'])) {
                $command = unserialize($data['data']['command']);
                if (method_exists($command, 'applyTenantContext')) {
                    $command->applyTenantContext();
                    \Illuminate\Support\Facades\Log::info("Global Queue Guard: Applied tenant context for job " . get_class($command));
                }
            }
        });
    }
}
