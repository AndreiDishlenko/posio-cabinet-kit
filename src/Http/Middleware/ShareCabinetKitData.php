<?php

namespace Posio\CabinetKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
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
        $this->applyLocale($request);

        Inertia::share('cabinetKitI18n', fn () => $this->i18nPayload());
        Inertia::share('serverlocale', fn () => app()->getLocale());

        if ($user) {
            $menu = fn () => app(MenuService::class)->menuFor($user);

            Inertia::share([
                'account' => fn () => $user->currentAccount()?->info(),
                'accounts' => fn () => $user->accessibleAccounts()->map->only(['id', 'name', 'owner_id']),
                'cabinetKitMenu' => $menu,
                'cabinetMenu' => $menu,
                'user' => fn () => $this->userPayload($user),
            ]);

            // SideMenu highlights the item whose id matches currentPage.id.
            // Only fill it in when the host doesn't share its own descriptor.
            if (! Inertia::getShared('currentPage')) {
                Inertia::share('currentPage', fn () => $this->currentPageDescriptor($request, $user));
            }
        }

        return $next($request);
    }

    protected function i18nPayload(): array
    {
        $locale = app()->getLocale();
        $fallbackLocale = config('cabinet-kit.translations.fallback_locale') ?: config('app.fallback_locale', 'en');

        return [
            'locale' => $locale,
            'fallbackLocale' => $fallbackLocale,
            'messages' => array_replace(
                $this->loadJsonTranslations($fallbackLocale),
                $this->loadJsonTranslations($locale),
            ),
            'locales' => $this->locales(),
        ];
    }

    protected function applyLocale(Request $request): void
    {
        $user = $request->user();
        $locale = ($user && method_exists($user, 'getSetting') ? $user->getSetting('locale') : null)
            ?: $request->session()->get('locale')
            ?: $request->cookie('locale');
        $locales = collect(config('cabinet-kit.translations.locales', []))
            ->keys()
            ->map(fn ($code) => (string) $code)
            ->all();

        if ($locale && in_array($locale, $locales, true)) {
            app()->setLocale($locale);
        }
    }

    protected function loadJsonTranslations(?string $locale): array
    {
        if (! $locale) {
            return [];
        }

        $messages = [];

        foreach ($this->translationPaths() as $path) {
            $file = rtrim($path, '/\\').DIRECTORY_SEPARATOR.$locale.'.json';

            if (! File::isFile($file)) {
                continue;
            }

            $translations = json_decode(File::get($file), true);

            if (is_array($translations)) {
                $messages = array_replace($messages, $translations);
            }
        }

        return $messages;
    }

    protected function translationPaths(): array
    {
        return collect(config('cabinet-kit.translations.json_paths', [lang_path()]))
            ->filter()
            ->map(fn ($path) => (string) $path)
            ->unique()
            ->values()
            ->all();
    }

    protected function locales(): array
    {
        return collect(config('cabinet-kit.translations.locales', []))
            ->map(fn ($locale, $code) => is_array($locale)
                ? array_merge(['code' => is_string($code) ? $code : Arr::get($locale, 'code')], $locale)
                : ['code' => is_string($code) ? $code : (string) $locale, 'name' => (string) $locale])
            ->filter(fn ($locale) => filled($locale['code'] ?? null))
            ->values()
            ->all();
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
