<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $modulesPath = app_path('Modules');

        if (!File::exists($modulesPath)) {
            File::makeDirectory($modulesPath, 0755, true);
            return;
        }

        $modules = File::directories($modulesPath);

        foreach ($modules as $modulePath) {
            $moduleName = basename($modulePath);
            
            // 1. Register Routes
            $routesPath = $modulePath . '/routes/api.php';
            if (File::exists($routesPath)) {
                Route::prefix('api')
                    ->middleware('api')
                    ->group($routesPath);
            }

            // 2. Register Migrations (for the master database if any, 
            // but usually module migrations are tenant-specific and handled by ModuleMigrationManager)
            $migrationsPath = $modulePath . '/database/migrations';
            if (File::exists($migrationsPath)) {
                $this->loadMigrationsFrom($migrationsPath);
            }

            // 3. Register Views
            $viewsPath = $modulePath . '/resources/views';
            if (File::exists($viewsPath)) {
                $this->loadViewsFrom($viewsPath, strtolower($moduleName));
            }
            
            // 4. Auto-load helper files if they exist
            $helperPath = $modulePath . '/helpers.php';
            if (File::exists($helperPath)) {
                require_once $helperPath;
            }
        }
    }
}
