<?php

namespace Posio\CabinetKit\Support;

use Illuminate\Support\Facades\Route;

/**
 * Resolves the auth flow's landing pages to real URLs.
 *
 * A host that names a page it later removed used to take the whole sign-in
 * flow down with it, so every target is checked against the registered routes
 * and an unresolvable one silently falls back to a page the package always
 * ships.
 */
class CabinetRedirects
{
    /**
     * Keys that used to live in the main config file, before landing pages got
     * one of their own. A host installed back then keeps its choice until the
     * dedicated file is created for it.
     */
    public const LEGACY_KEYS = [
        'home' => 'home_route',
        'after_login' => 'login_redirect_route',
        'after_register' => 'login_redirect_route',
        'after_verify' => 'login_redirect_route',
    ];

    protected static ?array $defaults = null;

    public static function url(string $key): string
    {
        foreach (static::candidates($key) as $target) {
            $target = trim((string) $target);

            if ($target === '') {
                continue;
            }

            if (static::isAddress($target)) {
                return $target;
            }

            if (Route::has($target)) {
                return route($target);
            }
        }

        return '/'.trim((string) config('cabinet-kit.route_prefix', 'cabinet'), '/');
    }

    /** Configured targets that name a route this application does not register. */
    public static function unresolvable(): array
    {
        $broken = [];

        foreach (array_keys(static::defaults()) as $key) {
            $target = trim((string) config("cabinet-kit-redirects.{$key}", ''));

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

    protected static function candidates(string $key): array
    {
        return [
            config("cabinet-kit-redirects.{$key}"),
            static::defaults()[$key] ?? null,
            // Last resort: the one bundled page no permission gates, so even an
            // account without a single system right lands somewhere real.
            config('cabinet-kit.route_name_prefix', 'cabinet-kit.').'settings',
        ];
    }

    protected static function isAddress(string $target): bool
    {
        return str_starts_with($target, '/')
            || str_starts_with($target, 'http://')
            || str_starts_with($target, 'https://');
    }
}
