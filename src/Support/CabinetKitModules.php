<?php

namespace Posio\CabinetKit\Support;

use Illuminate\Support\Facades\File;

/**
 * Модули кабинета — composer-пакеты, объявившие себя в `extra.cabinet-kit`:
 *
 *     "extra": {
 *         "cabinet-kit": {
 *             "module": "catalog",
 *             "admin": "resources/admin",
 *             "alias": "@catalog-kit",
 *             "npm": { "swiper": "^11.0.0" }
 *         }
 *     }
 *
 * Список берётся из того же файла установленных пакетов, что читает плагин
 * Vite, — сервер и сборка видят один и тот же набор модулей без регистрации
 * руками. Установленный модуль подключается фактом установки, удалённый —
 * исчезает вместе со своими страницами и маршрутами.
 */
class CabinetKitModules
{
    public const INSTALLED_JSON = 'vendor/composer/installed.json';

    protected static ?array $modules = null;

    /**
     * @return array<string, array{module: string, package: string, path: string, admin: ?string, alias: string, npm: array<string, string>, tailwind_glob: string}>
     */
    public static function all(): array
    {
        return static::$modules ??= static::discover();
    }

    public static function forget(): void
    {
        static::$modules = null;
    }

    /**
     * Корни страниц кабинета: в каждом лежит `pages/<модуль>/...`, поэтому
     * имя страницы Inertia (`pages/catalog/Products`) находится в них так же,
     * как в корнях самого пакета.
     *
     * @return string[]
     */
    public static function adminRoots(): array
    {
        return array_values(array_filter(array_column(static::all(), 'admin')));
    }

    protected static function discover(): array
    {
        $installed = base_path(self::INSTALLED_JSON);

        if (! File::isFile($installed)) {
            return [];
        }

        $json = json_decode(File::get($installed), true);
        // Composer 2 кладёт пакеты под ключ, composer 1 — списком в корне.
        $packages = is_array($json) ? ($json['packages'] ?? $json) : [];
        $modules = [];

        foreach ($packages as $package) {
            $extra = $package['extra']['cabinet-kit'] ?? null;

            if (! is_array($extra) || blank($extra['module'] ?? null) || blank($package['name'] ?? null)) {
                continue;
            }

            $name = (string) $package['name'];
            $path = static::installPath($package, $installed);
            $admin = filled($extra['admin'] ?? null) ? $path.DIRECTORY_SEPARATOR.trim((string) $extra['admin'], '/\\') : null;

            $modules[(string) $extra['module']] = [
                'module' => (string) $extra['module'],
                'package' => $name,
                'path' => $path,
                'admin' => $admin && File::isDirectory($admin) ? $admin : null,
                'alias' => (string) ($extra['alias'] ?? '@'.basename($name)),
                'npm' => array_filter((array) ($extra['npm'] ?? []), 'is_string'),
                'tailwind_glob' => "./vendor/{$name}/resources/**/*.{vue,js,ts}",
            ];
        }

        return $modules;
    }

    // Путь установки composer пишет относительно папки своего служебного файла.
    protected static function installPath(array $package, string $installed): string
    {
        $relative = $package['install-path'] ?? '../'.$package['name'];
        $path = dirname($installed).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

        return realpath($path) === false ? $path : static::normalize($path);
    }

    // realpath раскрыл бы junction модуля на время разработки, а исходники
    // модуля в хосте всё равно адресуются через vendor.
    protected static function normalize(string $path): string
    {
        $parts = [];

        foreach (preg_split('#[\\\\/]+#', $path) as $segment) {
            if ($segment === '..') {
                array_pop($parts);
            } elseif ($segment !== '.' && $segment !== '') {
                $parts[] = $segment;
            }
        }

        $prefix = str_starts_with($path, '/') ? DIRECTORY_SEPARATOR : '';

        return $prefix.implode(DIRECTORY_SEPARATOR, $parts);
    }
}
