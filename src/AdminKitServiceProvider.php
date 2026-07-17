<?php

namespace Posio\AdminKit;

use Illuminate\Support\ServiceProvider;
use Posio\AdminKit\Console\Commands\InstallCommand;
use Posio\AdminKit\Console\Commands\SyncConfigCommand;

class AdminKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin-kit.php', 'admin-kit');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');

        $this->publishes([
            __DIR__.'/../config/admin-kit.php' => config_path('admin-kit.php'),
        ], 'admin-kit-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'admin-kit-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                SyncConfigCommand::class,
            ]);
        }
    }
}
