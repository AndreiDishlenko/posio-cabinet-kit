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

        Inertia::share('cabinetKitI18n', fn () => $this->i18nPayload());

        if ($user) {
            Inertia::share([
                'account' => fn () => $user->currentAccount()?->info(),
                'accounts' => fn () => $user->accessibleAccounts()->map->only(['id', 'name', 'owner_id']),
                'cabinetKitMenu' => fn () => app(MenuService::class)->menuFor($user),
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

        foreach (app(MenuService::class)->menuFor($user) as $group) {
            foreach ($group['children'] ?? [] as $item) {
                if (($item['route'] ?? null) === $routeName) {
                    return [
                        'id' => $item['id'],
                        'name' => $item['label'] ?? null,
                        'section' => $group['label'] ?? null,
                    ];
                }
            }
        }

        return null;
    }
}
