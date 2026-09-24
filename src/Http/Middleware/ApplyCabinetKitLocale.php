<?php

namespace Posio\CabinetKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Posio\CabinetKit\Services\LocaleService;
use Symfony\Component\HttpFoundation\Response;

// Язык выбирается на всех маршрутах кабинета, включая гостевые: письма регистрации
// и сброса пароля уходят прямо из запроса и иначе получили бы язык приложения по умолчанию.
class ApplyCabinetKitLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $locale = ($user && method_exists($user, 'getSetting') ? $user->getSetting('locale') : null)
            ?: ($request->hasSession() ? $request->session()->get('locale') : null)
            ?: $request->cookie('locale');
        $locales = collect(config('cabinet-kit.translations.locales', []))
            ->keys()
            ->map(fn ($code) => (string) $code)
            ->all();

        // Первый визит без выбора: язык браузера, как на сайте, — иначе вход и
        // регистрация открылись бы на языке приложения по умолчанию.
        if (! $locale || ! in_array($locale, $locales, true)) {
            $locale = LocaleService::browserLocale((string) $request->server('HTTP_ACCEPT_LANGUAGE'));
        }

        if ($locale && in_array($locale, $locales, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
