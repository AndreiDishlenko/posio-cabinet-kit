<?php

namespace Posio\CabinetKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Posio\CabinetKit\CabinetKit;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the visitor language, the offered languages and the JSON translations
 * with Inertia. Cabinet routes get it through ShareCabinetKitData; a host adds it
 * to its public routes so the site switches language the same way the cabinet does.
 */
class ShareCabinetKitI18n
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->share();

        return $next($request);
    }

    public function share(): void
    {
        Inertia::share('cabinetKitI18n', fn () => $this->i18nPayload());
        Inertia::share('serverlocale', fn () => app()->getLocale());
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

    // Папки модулей идут первыми: одноимённый ключ хоста читается позже и выигрывает.
    protected function translationPaths(): array
    {
        return collect([
                ...app(CabinetKit::class)->translationPaths(),
                ...(array) config('cabinet-kit.translations.json_paths', [lang_path()]),
            ])
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
}
