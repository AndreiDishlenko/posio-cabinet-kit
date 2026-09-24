<?php

namespace Posio\CabinetKit\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Куда вести пользователя на каждом шаге авторизации и при отказе в доступе.
 *
 * Целевая страница — запомненная (куда шёл) либо назначенная в карте перенаправлений.
 * Если её нет или пользователю не хватает на неё прав — ведём в профиль: это страница
 * кабинета без проверки прав, она открыта всегда.
 *
 * A host that names a page it later removed used to take the whole sign-in
 * flow down with it, so a target naming no registered route falls back to the
 * package default before the profile.
 */
class CabinetRedirects
{
    protected const CONFIG = 'cabinet-kit-redirects';

    /**
     * Keys that used to live in the main config file, before landing pages got
     * one of their own. A host installed back then keeps its choice until the
     * dedicated file is created for it.
     */
    public const LEGACY_KEYS = [
        'home' => 'home_route',
        'after_login' => 'login_redirect_route',
        'after_verify' => 'login_redirect_route',
    ];

    protected static ?array $defaults = null;

    // Назначенная страница шага, если она открыта пользователю, иначе профиль.
    public static function url(string $key): string
    {
        foreach (static::candidates($key) as $target) {
            $url = static::resolve((string) $target);

            if ($url !== null) {
                return $url;
            }
        }

        return static::profile();
    }

    // Куда шёл пользователь; без запомненного адреса — предпочтённая или назначенная
    // страница шага. Закрытая цель ведёт в профиль, а не на следующую по списку.
    public static function intended(string $key, ?string $preferred = null): string
    {
        $intended = (string) session()->pull('url.intended', '');

        if ($intended !== '') {
            return PageAccess::allowsUrl($intended) ? $intended : static::profile();
        }

        if ($preferred) {
            return static::resolve($preferred) ?? static::profile();
        }

        return static::url($key);
    }

    public static function profile(): string
    {
        return route(config(static::CONFIG.'.profile'));
    }

    // Не хватило прав на страницу — ведём в профиль вместо голого 403.
    // Запросы данных и действия остаются с отказом.
    public static function deniedPage(Request $request): ?RedirectResponse
    {
        if (! $request->isMethod('GET') || $request->expectsJson() || ! $request->user()) {
            return null;
        }

        $profile = static::profile();

        // Отказ на самом профиле — оставляем 403, иначе перенаправление зациклится.
        if ($request->url() === strtok($profile, '?')) {
            return null;
        }

        return redirect($profile);
    }

    // Подпись ссылки подтверждения почты не сходится — чаще всего почтовая программа обрезала
    // или перенесла адрес. Для получателя это испорченная ссылка, а не отказ в доступе.
    // HEAD — предварительная проверка ссылки почтовыми сканерами, отвечаем им так же.
    public static function brokenVerificationLink(Request $request): ?RedirectResponse
    {
        if (! in_array($request->method(), ['GET', 'HEAD']) || $request->expectsJson()) {
            return null;
        }

        if (! $request->routeIs('verification.verify')) {
            return null;
        }

        return redirect()->route('login')->with('status', 'verification-link-broken');
    }

    /** Configured targets that name a route this application does not register. */
    public static function unresolvable(): array
    {
        $broken = [];

        foreach (array_keys(static::defaults()) as $key) {
            $target = trim((string) config(static::CONFIG.".{$key}", ''));

            if ($target === '' || static::isAddress($target) || Route::has($target)) {
                continue;
            }

            $broken[$key] = $target;
        }

        return $broken;
    }

    public static function defaults(): array
    {
        return static::$defaults ??= require __DIR__.'/../../config/cabinet-kit-redirects.php';
    }

    // Готовый адрес берётся как задан; имя маршрута — только существующего и открытого пользователю.
    protected static function resolve(string $target): ?string
    {
        $target = trim($target);

        if ($target === '') {
            return null;
        }

        if (static::isAddress($target)) {
            return $target;
        }

        if (! Route::has($target) || ! PageAccess::allowsRoute(Route::getRoutes()->getByName($target))) {
            return null;
        }

        return route($target);
    }

    protected static function candidates(string $key): array
    {
        return [
            config(static::CONFIG.".{$key}"),
            static::defaults()[$key] ?? null,
        ];
    }

    protected static function isAddress(string $target): bool
    {
        return str_starts_with($target, '/')
            || str_starts_with($target, 'http://')
            || str_starts_with($target, 'https://');
    }
}
