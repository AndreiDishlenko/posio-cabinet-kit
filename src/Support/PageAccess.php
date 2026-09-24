<?php

namespace Posio\CabinetKit\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

// Откроется ли страница текущему пользователю: адрес ведёт на существующую GET-страницу,
// и все проверки прав на её маршруте его пропускают.
class PageAccess
{
    public static function allowsUrl(string $url): bool
    {
        $route = static::routeFor($url);

        return $route !== null && static::allowsRoute($route);
    }

    public static function allowsRoute(Route $route): bool
    {
        $aliases = app('router')->getMiddleware();

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            [$name, $parameters] = array_pad(explode(':', $middleware, 2), 2, '');
            $class = $aliases[$name] ?? $name;

            if (is_subclass_of($class, PermissionGate::class) && ! $class::allows(Auth::user(), $parameters)) {
                return false;
            }
        }

        return true;
    }

    protected static function routeFor(string $url): ?Route
    {
        try {
            return app('router')->getRoutes()->match(Request::create(url($url), 'GET'));
        } catch (HttpExceptionInterface) {
            return null;
        }
    }
}
