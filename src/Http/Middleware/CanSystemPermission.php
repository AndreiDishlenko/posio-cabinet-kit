<?php

namespace Posio\CabinetKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Posio\CabinetKit\Support\PermissionGate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

// Отказ — именно AccessDeniedHttpException: на него откликается общее правило
// «не хватило прав на страницу — в профиль», запросы данных остаются с 403.
class CanSystemPermission implements PermissionGate
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! static::allows(Auth::user(), $permission)) {
            throw new AccessDeniedHttpException();
        }

        return $next($request);
    }

    public static function allows($user, string $parameters): bool
    {
        return (bool) $user?->canSystem($parameters);
    }
}
