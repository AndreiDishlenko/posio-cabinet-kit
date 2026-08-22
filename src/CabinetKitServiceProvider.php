<?php

namespace Posio\CabinetKit;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;
use Posio\CabinetKit\Console\Commands\DoctorCommand;
use Posio\CabinetKit\Console\Commands\InstallCommand;
use Posio\CabinetKit\Console\Commands\SyncConfigCommand;
use Posio\CabinetKit\Http\Middleware\RequireSystemPasswordChange;
use Posio\CabinetKit\Support\CabinetRedirects;

class CabinetKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cabinet-kit.php', 'cabinet-kit');
        $this->mergeConfigFrom(__DIR__.'/../config/cabinet-kit-redirects.php', 'cabinet-kit-redirects');

        $this->bridgeLegacyRedirects();
        $this->mountLogViewer();
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/cabinet.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cabinet-kit');

        $this->registerInertiaPagePaths();
        $this->registerSocialAuth();
        $this->registerLogViewerAuth();

        // Aliased so a host can hold its own route groups behind the same gate —
        // the package can only speak for its own routes.
        $this->app['router']->aliasMiddleware('cabinet-kit.system-password', RequireSystemPasswordChange::class);

        $this->publishes([
            __DIR__.'/../config/cabinet-kit.php' => config_path('cabinet-kit.php'),
            __DIR__.'/../config/cabinet-kit-redirects.php' => config_path('cabinet-kit-redirects.php'),
        ], 'cabinet-kit-config');

        $this->publishes([
            __DIR__.'/../config/cabinet-kit-redirects.php' => config_path('cabinet-kit-redirects.php'),
        ], 'cabinet-kit-redirects');

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
     * A host installed before landing pages got a config file of their own
     * still keeps its choice in the main one. Those keys stay authoritative
     * until the dedicated file exists, so an update alone never moves a
     * cabinet's landing page behind the host's back.
     */
    protected function bridgeLegacyRedirects(): void
    {
        if (file_exists(config_path('cabinet-kit-redirects.php'))) {
            return;
        }

        foreach (CabinetRedirects::LEGACY_KEYS as $key => $legacyKey) {
            $target = config("cabinet-kit.{$legacyKey}");

            if (filled($target)) {
                config(["cabinet-kit-redirects.{$key}" => $target]);
            }
        }
    }

    /**
     * The bundled log viewer is a plain page of its own, not an Inertia one:
     * the Logs menu item is a bare href at the path set here, so both have to
     * agree. Its own config file is never published (only cabinet-kit.php is),
     * hence the path is written straight into the runtime config — before the
     * viewer's provider boots and reads it. A host that did publish that config
     * owns the setting and is left alone.
     */
    protected function mountLogViewer(): void
    {
        if (file_exists(config_path('log-viewer.php'))) {
            return;
        }

        $path = trim((string) config('cabinet-kit.log_viewer.route_path', ''), '/');

        if ($path === '') {
            return;
        }

        config(['log-viewer.route_path' => $path]);
    }

    /**
     * Reading the application log is a platform-operator power, so it is gated
     * by the same system permission as the menu item leading to it. A host that
     * decides access for itself keeps that decision.
     */
    protected function registerLogViewerAuth(): void
    {
        if (LogViewer::hasAuthCallback() || Gate::has('viewLogViewer')) {
            return;
        }

        LogViewer::auth(function (): bool {
            $user = Auth::user();

            if (! $user) {
                return false;
            }

            return method_exists($user, 'canSystem')
                ? $user->canSystem('sysper-log-view')
                : $user->can('sysper-log-view');
        });
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
