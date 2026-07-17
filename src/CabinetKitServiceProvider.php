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
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cabinet-kit');

        $this->registerInertiaPagePaths();

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

    /**
     * Teach Inertia's server-side view-finder where CabinetKit pages live.
     * Without this, any host with `inertia.pages.ensure_pages_exist => true`
     * (inertia-laravel v3) 500s with ComponentNotFoundException on every
     * package page ("pages/Auth/Login" etc.), because the default paths only
     * cover the host's own resources. The override folder goes first so a
     * host override is also visible to the finder.
     */
    protected function registerInertiaPagePaths(): void
    {
        $packagePages = dirname(__DIR__).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'js';
        $overridePages = resource_path('_admin/overrides');

        // inertia-laravel v3: runtime ensure_pages_exist + assertInertia share these paths.
        $paths = config('inertia.pages.paths');
        if (is_array($paths)) {
            config(['inertia.pages.paths' => array_values(array_unique(array_merge($paths, [$overridePages, $packagePages])))]);
        }

        // inertia-laravel v1/v2: only test assertions look pages up, under a different key.
        $testingPaths = config('inertia.testing.page_paths');
        if (is_array($testingPaths)) {
            config(['inertia.testing.page_paths' => array_values(array_unique(array_merge($testingPaths, [$overridePages, $packagePages])))]);
        }
    }
}
