<?php

namespace Posio\CabinetKit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array modules()
 * @method static array cabinetMiddleware()
 * @method static void cabinetRoutes(\Closure $routes)
 * @method static void apiRoutes(\Closure $routes)
 * @method static void systemPermissions(array $names)
 * @method static array registeredSystemPermissions()
 * @method static void dictionaries(string $source, \Closure $provider)
 * @method static void translations(string $path)
 * @method static void sitemap(\Closure $provider)
 * @method static void menu(array $group)
 * @method static void doctor(string $module, \Closure $checks)
 * @method static void syncConfig(string $module, \Closure $step)
 * @method static string routePrefix()
 *
 * @see \Posio\CabinetKit\CabinetKit
 */
class CabinetKit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Posio\CabinetKit\CabinetKit::class;
    }
}
