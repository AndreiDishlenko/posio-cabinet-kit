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

        app(ShareCabinetKitI18n::class)->share();

        if ($user) {
            $menu = fn () => app(MenuService::class)->menuFor($user);

            Inertia::share([
                'account' => fn () => $user->currentAccount()?->info(),
                'accounts' => fn () => $user->accessibleAccounts()->map->only(['id', 'name', 'owner_id']),
                'cabinetKitMenu' => $menu,
                'cabinetMenu' => $menu,
                'user' => fn () => $this->userPayload($user),
                // Шаги после регистрации: тур, подсказки, поздравление — layout показывает
                // только включённые.
                'onboarding' => fn () => config('cabinet_onboarding'),
            ]);

            // SideMenu highlights the item whose id matches currentPage.id.
            // Only fill it in when the host doesn't share its own descriptor.
            if (! Inertia::getShared('currentPage')) {
                Inertia::share('currentPage', fn () => $this->currentPageDescriptor($request, $user));
            }
        }

        return $next($request);
    }

    protected function currentPageDescriptor(Request $request, $user): ?array
    {
        $routeName = $request->route()?->getName();

        if (! $routeName) {
            return null;
        }

        return app(MenuService::class)->currentPage($routeName, $user);
    }

    protected function userPayload($user): array
    {
        $account = $user->currentAccount();

        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar ?? null,
            'account' => $user->ownAccount()?->id,
            'can_manage_members' => $account ? $user->can('manage-members') : false,
            'can_manage_account' => $account ? $user->can('manage-account') : false,
            'tour_done' => method_exists($user, 'getSetting') ? (bool) $user->getSetting('tour_done') : true,
            'play_notifications' => method_exists($user, 'getSetting') ? (bool) $user->getSetting('play_notifications') : false,
        ];
    }
}
