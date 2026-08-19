<?php

namespace Posio\CabinetKit;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Posio\CabinetKit\Console\Commands\DoctorCommand;
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
        $this->registerSocialAuth();

        $this->publishes([
            __DIR__.'/../config/cabinet-kit.php' => config_path('cabinet-kit.php'),
        ], 'cabinet-kit-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'cabinet-kit-migrations');

        $this->publishes([
            __DIR__.'/../public/cabinet-assets' => public_path('cabinet-assets'),
        ], 'cabinet-kit-assets');

        if ($this->app->runningInConsole()) {
            $this->commands([
                DoctorCommand::class,
                InstallCommand::class,
                SyncConfigCommand::class,
            ]);
        }
    }

    /**
     * Bridge the bundled social sign-in credentials into the shape Socialite
     * reads, so a consumer only fills in env vars — config/services.php is the
     * host's file and this package never publishes into it. Anything the host
     * already declares there wins and is left alone.
     */
    protected function registerSocialAuth(): void
    {
        $prefix = trim((string) config('cabinet-kit.route_prefix', 'cabinet'), '/');

        foreach ((array) config('cabinet-kit.social_auth', []) as $provider => $credentials) {
            $credentials = (array) $credentials;

            if (blank($credentials['client_id'] ?? null) || filled(config("services.{$provider}.client_id"))) {
                continue;
            }

            if (blank($credentials['redirect'] ?? null)) {
                $credentials['redirect'] = '/'.ltrim($prefix.'/auth/'.$provider.'/callback', '/');
            }

            config(["services.{$provider}" => array_merge((array) config("services.{$provider}", []), $credentials)]);
        }

        // Apple is not one of Socialite's own drivers — hook up the community
        // one when the host has pulled it in.
        if (class_exists(\SocialiteProviders\Apple\Provider::class)) {
            Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
                $event->extendSocialite('apple', \SocialiteProviders\Apple\Provider::class);
            });
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
        $overridePages = resource_path(config('cabinet-kit.overrides_path', '_admin/overrides'));

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
