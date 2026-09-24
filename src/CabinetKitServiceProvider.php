<?php

namespace Posio\CabinetKit;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Opcodes\LogViewer\Facades\LogViewer;
use Posio\CabinetKit\Console\Commands\DoctorCommand;
use Posio\CabinetKit\Console\Commands\GenerateSitemap;
use Posio\CabinetKit\Console\Commands\ImportSiteBrand;
use Posio\CabinetKit\Console\Commands\InstallCommand;
use Posio\CabinetKit\Console\Commands\SyncConfigCommand;
use Posio\CabinetKit\Console\Commands\TestCommand;
use Posio\CabinetKit\Services\SeoService;
use Posio\CabinetKit\Services\SiteSettingsService;
use Posio\CabinetKit\Http\Middleware\ApplyCabinetKitLocale;
use Posio\CabinetKit\Http\Middleware\RequireSystemPasswordChange;
use Posio\CabinetKit\Http\Middleware\ShareCabinetKitI18n;
use Posio\CabinetKit\Notifications\AuthMail;
use Posio\CabinetKit\Support\CabinetKitModules;
use Posio\CabinetKit\Support\CabinetKitRoles;
use Posio\CabinetKit\Support\CabinetRedirects;

class CabinetKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cabinet-kit.php', 'cabinet-kit');
        $this->mergeSocialAuthEnabledDefaults();
        $this->mergeConfigFrom(__DIR__.'/../config/cabinet-kit-redirects.php', 'cabinet-kit-redirects');
        // Оба конфига носят имена, под которыми их читает перенесённый SEO-код.
        // Слияние оставляет за хостом каждый ключ, который он объявил сам.
        $this->mergeConfigFrom(__DIR__.'/../config/seo.php', 'seo');
        $this->mergeConfigFrom(__DIR__.'/../config/general.php', 'general');
        // Шаги после регистрации — под именем источника, чтобы перенесённый код читал их как есть.
        $this->mergeConfigFrom(__DIR__.'/../config/cabinet_onboarding.php', 'cabinet_onboarding');

        $this->bridgeLegacyRedirects();
        $this->mountLogViewer();

        $this->app->singleton(CabinetKit::class);
        // Переопределения меты от контроллера живут ровно один запрос.
        $this->app->scoped(SeoService::class);
    }

    /**
     * Laravel merges package config only at the first array level. Existing
     * consumers already own the whole social_auth array, so a newly introduced
     * nested enabled key would otherwise never reach them from the package
     * defaults or its environment variable.
     */
    protected function mergeSocialAuthEnabledDefaults(): void
    {
        $defaults = (array) (require __DIR__.'/../config/cabinet-kit.php')['social_auth'];

        foreach ($defaults as $provider => $settings) {
            $key = "cabinet-kit.social_auth.{$provider}.enabled";

            if (! $this->app['config']->has($key)) {
                $this->app['config']->set($key, (bool) ($settings['enabled'] ?? true));
            }
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/cabinet.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cabinet-kit');
        // Переводы пакета — запасной слой: одноимённые файлы и ключи хоста
        // (lang/vendor/cabinet-kit/… и lang/{локаль}.json) читаются поверх.
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'cabinet-kit');
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        $this->registerInertiaPagePaths();
        AuthMail::register();
        $this->registerCabinetRedirects();
        $this->registerSocialAuth();
        $this->registerLogViewerAuth();
        $this->registerSiteSettings();
        $this->registerSeoSharing();
        $this->registerRolesSync();

        // Aliased so a host can hold its own route groups behind the same gate —
        // the package can only speak for its own routes.
        $this->app['router']->aliasMiddleware('cabinet-kit.system-password', RequireSystemPasswordChange::class);
        // Язык публичных страниц хоста — тот же, что у кабинета.
        $this->app['router']->aliasMiddleware('cabinet-kit.locale', ApplyCabinetKitLocale::class);
        $this->app['router']->aliasMiddleware('cabinet-kit.i18n', ShareCabinetKitI18n::class);

        $this->publishes([
            __DIR__.'/../config/cabinet-kit.php' => config_path('cabinet-kit.php'),
            __DIR__.'/../config/cabinet-kit-redirects.php' => config_path('cabinet-kit-redirects.php'),
        ], 'cabinet-kit-config');

        $this->publishes([
            __DIR__.'/../config/cabinet-kit-redirects.php' => config_path('cabinet-kit-redirects.php'),
        ], 'cabinet-kit-redirects');

        // Оба файла — правки хоста: контакты организации, состав главной навигации,
        // список локалей. Существующие не перезаписываются публикацией.
        $this->publishes([
            __DIR__.'/../config/seo.php' => config_path('seo.php'),
            __DIR__.'/../config/general.php' => config_path('general.php'),
        ], 'cabinet-kit-seo-config');

        $this->publishes([
            __DIR__.'/../config/cabinet_onboarding.php' => config_path('cabinet_onboarding.php'),
        ], 'cabinet-kit-onboarding');

        // Публикация нужна только ради правки: без неё письма берут тексты и шаблоны из пакета.
        $this->publishes([
            __DIR__.'/../lang/uk/mail.php' => lang_path('vendor/cabinet-kit/uk/mail.php'),
            __DIR__.'/../lang/en/mail.php' => lang_path('vendor/cabinet-kit/en/mail.php'),
            __DIR__.'/../resources/views/mail' => resource_path('views/vendor/cabinet-kit/mail'),
        ], 'cabinet-kit-mail');

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
                ImportSiteBrand::class,
                TestCommand::class,
            ]);
        }

        // Вне консольной ветки: кнопка раздела SEO вызывает команду из веб-запроса.
        $this->commands([GenerateSitemap::class]);
    }

    /**
     * Название, значок вкладки и тема по умолчанию доезжают до разметки двумя
     * путями: переменными Blade (до монтирования Vue — иначе вкладка мигает
     * чужим значком и светлой темой) и пропом Inertia с логотипами для шапки
     * сайта и бокового меню кабинета.
     *
     * Набор Blade-вьюх задаёт хост: своих шаблонов публичной части у пакета нет.
     */
    protected function registerSiteSettings(): void
    {
        $views = (array) config('cabinet-kit.site.views', []);

        if ($views !== []) {
            View::composer(array_keys($views), function ($view) use ($views) {
                $scope = $views[$view->getName()] ?? 'main';
                $settings = app(SiteSettingsService::class);

                $view->with([
                    'site_name' => $this->safely(fn () => $settings->siteName(), config('app.name', 'Cabinet')),
                    // Часть приложения, чьё оформление принадлежит ей самой,
                    // получает только название: пустые значок и тема оставляют
                    // работать запасные пути её собственного шаблона.
                    'site_favicon' => $scope ? $this->safely(fn () => $settings->imageUrl($scope.'_favicon')) : null,
                    'site_theme' => $scope ? $this->safely(fn () => $settings->theme($scope.'_theme'), 'dark') : null,
                    'site_share_image' => $scope ? $this->safely(fn () => $settings->shareImageUrl($scope)) : null,
                ]);
            });
        }

        if (config('cabinet-kit.site.share_prop', true)) {
            Inertia::share('site', fn () => $this->safely(
                fn () => app(SiteSettingsService::class)->frontPayload(),
                [],
            ));
        }
    }

    /**
     * Сидеры запускаются только установщиком, а роли и права менялись и после
     * первой установки — хост, поставленный раньше, остался бы с пустыми
     * матрицами ролей. Накат миграций завершает любое обновление пакета, поэтому
     * сверка висит на нём, в том числе когда новых миграций нет.
     */
    protected function registerRolesSync(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        Event::listen([MigrationsEnded::class, NoPendingMigrations::class], function ($event) {
            if ($event->method !== 'up' || ($event->options['pretend'] ?? false)) {
                return;
            }

            CabinetKitRoles::sync();
        });
    }

    /**
     * Мета публичных страниц считается на каждый полный рендер и уезжает одним
     * пропом — его читает единственный компонент меты внутри layout сайта.
     */
    protected function registerSeoSharing(): void
    {
        if (! config('cabinet-kit.seo.share_prop', true)) {
            return;
        }

        Inertia::share('seo', fn () => $this->safely(
            fn () => app(SeoService::class)->getInfo(),
            [],
        ));
    }

    /**
     * Настройки и мета читаются из базы, а рендер страницы не должен падать
     * из-за ещё не накатанной миграции или недоступной базы: тогда работают
     * заготовки.
     */
    protected function safely(callable $resolve, $fallback = null)
    {
        try {
            return $resolve();
        } catch (\Throwable) {
            return $fallback;
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
     * Не хватило прав на страницу — профиль вместо голого 403; испорченная ссылка
     * подтверждения почты — вход с объяснением. Правила общие с posio.cabinet.
     */
    protected function registerCabinetRedirects(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        if (! method_exists($handler, 'renderable')) {
            return;
        }

        $handler->renderable(fn (AccessDeniedHttpException $e, Request $request) => CabinetRedirects::deniedPage($request));
        $handler->renderable(fn (InvalidSignatureException $e, Request $request) => CabinetRedirects::brokenVerificationLink($request));
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

        // Страницы входа прячут ссылку на регистрацию, когда она закрыта.
        Inertia::share('registration_open', fn () => \Posio\CabinetKit\Support\RegistrationMode::allowsSignUp());

        Inertia::share('social_auth', fn () => [
            'google' => [
                'enabled' => \Posio\CabinetKit\Support\RegistrationMode::allowsSignUp()
                    && (bool) config('cabinet-kit.social_auth.google.enabled', true)
                    && filled(config('services.google.client_id'))
                    && class_exists(\Laravel\Socialite\Facades\Socialite::class),
            ],
        ]);
    }

    /**
     * Teach Inertia's server-side view-finder where CabinetKit pages live.
     * Without this, any host with `inertia.pages.ensure_pages_exist => true`
     * (inertia-laravel v3) 500s with ComponentNotFoundException on every
     * package page ("pages/Auth/Login" etc.), because the default paths only
     * cover the host's own resources. The override folder goes first so a
     * host override is also visible to the finder.
     *
     * Both roots the client-side resolver globs have to be listed: auth pages
     * live under resources/js, the cabinet's own (settings, users, permissions,
     * the system-password screen) under resources/_admin/js. A root left out
     * here renders fine in the browser and 500s server-side.
     *
     * Installed modules add their own roots, in the same order the client
     * resolver checks them: after the overrides, before the package.
     */
    protected function registerInertiaPagePaths(): void
    {
        $resources = dirname(__DIR__).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR;
        $pageRoots = [
            resource_path(config('cabinet-kit.overrides_path', '_admin/overrides')),
            ...CabinetKitModules::adminRoots(),
            $resources.'js',
            $resources.'_admin'.DIRECTORY_SEPARATOR.'js',
        ];

        // inertia-laravel v3: runtime ensure_pages_exist + assertInertia share these paths.
        $paths = config('inertia.pages.paths');
        if (is_array($paths)) {
            config(['inertia.pages.paths' => array_values(array_unique(array_merge($paths, $pageRoots)))]);
        }

        // inertia-laravel v1/v2: only test assertions look pages up, under a different key.
        $testingPaths = config('inertia.testing.page_paths');
        if (is_array($testingPaths)) {
            config(['inertia.testing.page_paths' => array_values(array_unique(array_merge($testingPaths, $pageRoots)))]);
        }
    }
}
