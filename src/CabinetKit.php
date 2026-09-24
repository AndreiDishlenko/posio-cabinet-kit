<?php

namespace Posio\CabinetKit;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Posio\CabinetKit\Http\Middleware\ApplyCabinetKitLocale;
use Posio\CabinetKit\Http\Middleware\NewAuth;
use Posio\CabinetKit\Http\Middleware\NotVerified;
use Posio\CabinetKit\Http\Middleware\RequireRegistrationApproval;
use Posio\CabinetKit\Http\Middleware\RequireSystemPasswordChange;
use Posio\CabinetKit\Http\Middleware\SetPermissionTeam;
use Posio\CabinetKit\Http\Middleware\ShareCabinetKitData;
use Posio\CabinetKit\Http\Middleware\UseCabinetKitRootView;
use Posio\CabinetKit\Support\CabinetKitModules;

/**
 * Точки встраивания модулей (и хоста) в кабинет: маршруты под стеком кабинета,
 * системные права, справочники, переводы, карта сайта, меню и диагностика.
 *
 * Модуль вызывает их из boot() своего провайдера; страницы и алиас сборки
 * пакет находит сам по `extra.cabinet-kit` модуля.
 */
class CabinetKit
{
    protected array $systemPermissions = [];

    /** @var array<string, Closure[]> */
    protected array $dictionaries = [];

    protected array $translationPaths = [];

    /** @var Closure[] */
    protected array $sitemapProviders = [];

    protected array $menuGroups = [];

    /** @var array<string, Closure[]> */
    protected array $doctorChecks = [];

    /** @var array<string, Closure[]> */
    protected array $syncConfigSteps = [];

    public function __construct(protected Application $app)
    {
    }

    public function modules(): array
    {
        return CabinetKitModules::all();
    }

    /**
     * Стек авторизованной группы кабинета. Подтверждение почты, одобрение
     * регистрации и смена пароля встроенных учёток не настраиваются: хост и
     * модуль не должны открывать кабинет в обход них. Страница запоминается
     * до проверки почты: после подтверждения пользователь вернётся на неё.
     */
    public function cabinetMiddleware(): array
    {
        return array_merge(
            config('cabinet-kit.middleware', ['web', 'auth']),
            [NewAuth::class, NotVerified::class, RequireRegistrationApproval::class, SetPermissionTeam::class, ShareCabinetKitData::class, RequireSystemPasswordChange::class],
        );
    }

    /** Страницы кабинета: префикс, оболочка и весь стек авторизованной группы. */
    public function cabinetRoutes(Closure $routes): void
    {
        $this->registerRoutes(function () use ($routes) {
            Route::middleware(['web', UseCabinetKitRootView::class, ApplyCabinetKitLocale::class])
                ->prefix($this->routePrefix())
                ->group(fn () => Route::middleware($this->cabinetMiddleware())->group($routes));
        });
    }

    /** Запросы страниц кабинета за данными: тот же стек под префиксом `api`, без оболочки страницы. */
    public function apiRoutes(Closure $routes): void
    {
        $this->registerRoutes(function () use ($routes) {
            Route::middleware(['web', ApplyCabinetKitLocale::class])
                ->prefix($this->routePrefix().'/api')
                ->group(fn () => Route::middleware($this->cabinetMiddleware())->group($routes));
        });
    }

    /**
     * Системные права модуля. Создаются той же сверкой после миграций, что и
     * права пакета, и при создании выдаются суперадминистратору и системному
     * администратору.
     */
    public function systemPermissions(array $names): void
    {
        $this->systemPermissions = array_values(array_unique([...$this->systemPermissions, ...$names]));
    }

    public function registeredSystemPermissions(): array
    {
        return $this->systemPermissions;
    }

    /**
     * Справочники кабинета от модуля или хоста: замыкание возвращает
     * `[имя справочника => данные]`. Общий эндпоинт пакета сливает их всех.
     */
    public function dictionaries(string $source, Closure $provider): void
    {
        $this->dictionaries[$source][] = $provider;
    }

    /** @return array<string, Closure[]> */
    public function dictionaryProviders(): array
    {
        return $this->dictionaries;
    }

    /** Папка JSON-переводов модуля для `$t()` в кабинете; одноимённые ключи хоста выигрывают. */
    public function translations(string $path): void
    {
        $this->translationPaths[] = $path;
    }

    public function translationPaths(): array
    {
        return array_values(array_unique($this->translationPaths));
    }

    /** Поставщик адресов карты сайта: получает её объект и дописывает свои адреса. */
    public function sitemap(Closure $provider): void
    {
        $this->sitemapProviders[] = $provider;
    }

    /** @return Closure[] */
    public function sitemapProviders(): array
    {
        return $this->sitemapProviders;
    }

    /**
     * Группа меню на случай, когда меню хоста задано конфигом, а не таблицей.
     * Формат — как у групп `cabinet-kit.menu`.
     */
    public function menu(array $group): void
    {
        $this->menuGroups[] = $group;
    }

    public function menuGroups(): array
    {
        return $this->menuGroups;
    }

    /**
     * Проверки модуля в `cabinet-kit:doctor`: замыкание возвращает список
     * `[bool ок, string что проверено, string что сделать]`.
     */
    public function doctor(string $module, Closure $checks): void
    {
        $this->doctorChecks[$module][] = $checks;
    }

    /** @return array<string, Closure[]> */
    public function doctorChecks(): array
    {
        return $this->doctorChecks;
    }

    /** Шаг модуля в `cabinet-kit:sync-config`: получает команду, чтобы писать в её вывод. */
    public function syncConfig(string $module, Closure $step): void
    {
        $this->syncConfigSteps[$module][] = $step;
    }

    /** @return array<string, Closure[]> */
    public function syncConfigSteps(): array
    {
        return $this->syncConfigSteps;
    }

    public function routePrefix(): string
    {
        return trim((string) config('cabinet-kit.route_prefix', 'cabinet'), '/');
    }

    // Как у штатной загрузки маршрутов пакета: закэшированные маршруты уже
    // содержат модульные, повторная регистрация задвоила бы их.
    protected function registerRoutes(Closure $register): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $register();

        $this->app->booted(function () {
            $this->app['router']->getRoutes()->refreshNameLookups();
            $this->app['router']->getRoutes()->refreshActionLookups();
        });
    }
}
