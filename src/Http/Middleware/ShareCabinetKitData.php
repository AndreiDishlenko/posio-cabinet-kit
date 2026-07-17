<?php

namespace Posio\CabinetKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Posio\CabinetKit\Services\MenuService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the Inertia props every CabinetKit page/layout reads:
 * account, accounts (switcher), cabinetKitMenu. Mirrors what
 * HandleInertiaRequests does for user/currentPage in the host app.
 */
class ShareCabinetKitData
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            Inertia::share([
                'account' => fn () => $user->currentAccount()?->info(),
                'accounts' => fn () => $user->accessibleAccounts()->map->only(['id', 'name', 'owner_id']),
                'cabinetKitMenu' => fn () => app(MenuService::class)->menuFor($user),
            ]);
        }

        return $next($request);
    }
}
