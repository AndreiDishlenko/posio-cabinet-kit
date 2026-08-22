<?php

namespace Posio\CabinetKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Posio\CabinetKit\Support\SystemPasswordPolicy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate in front of the cabinet for an account still holding its seeded
 * password: every route of the group leads to the change form and nowhere
 * else. Mirrors how a user without an account is held on the initialization
 * screen — same shape, same place in the middleware stack.
 *
 * Registered under the `cabinet-kit.system-password` alias too, so a host can
 * put the same gate in front of its own route groups.
 */
class RequireSystemPasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! SystemPasswordPolicy::mustChangePassword($user)) {
            return $next($request);
        }

        // The form itself has to stay reachable, or the redirect chases its own tail.
        if (in_array($request->route()?->getName(), static::formRoutes(), true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Set your own password before using this account.');
        }

        return redirect()->route(static::formRoutes()[0]);
    }

    protected static function formRoutes(): array
    {
        $prefix = config('cabinet-kit.route_name_prefix', 'cabinet-kit.');

        return [$prefix.'system-password', $prefix.'system-password.update'];
    }
}
