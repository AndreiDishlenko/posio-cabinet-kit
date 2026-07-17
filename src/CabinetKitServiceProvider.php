<?php

namespace Posio\CabinetKit;

use Illuminate\Support\ServiceProvider;
use Posio\CabinetKit\Console\Commands\InstallCommand;
use Posio\CabinetKit\Console\Commands\SyncConfigCommand;

class CabinetKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cabinet-kit.php', 'cabinet-kit');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/cabinet.php');

        $this->publishes([
            __DIR__.'/../config/cabinet-kit.php' => config_path('cabinet-kit.php'),
        ], 'cabinet-kit-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'cabinet-kit-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                SyncConfigCommand::class,
            ]);
        }
    }
}
