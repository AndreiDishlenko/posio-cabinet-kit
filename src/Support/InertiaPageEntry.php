<?php

namespace Posio\CabinetKit\Support;

use Illuminate\Support\Facades\Vite;

// Файл страницы Inertia для предзагрузки корневым шаблоном хоста. Страница может лежать
// и у хоста, и в пакете, поэтому путь «каталог хоста + имя» уже не годится: собранная
// версия не находит такой файл в манифесте и отвечает ошибкой на каждую пакетную страницу.
class InertiaPageEntry
{
    protected static ?array $manifest = null;

    // Каталоги страниц перечисляются от корня проекта в том же порядке, что и в клиентском
    // поиске страниц, — иначе предзагрузится не тот файл, который отрисует браузер.
    // Страница, которой нет в сборке, не предзагружается: браузер всё равно загрузит её сам.
    public static function path(string $component, array $roots): ?string
    {
        $file = $component.'.vue';

        foreach ($roots as $root) {
            $candidate = trim($root, '/').'/'.$file;

            if (! is_file(base_path($candidate))) {
                continue;
            }

            // Dev-сервер отдаёт файл по пути от корня проекта, манифеста у него нет.
            if (Vite::isRunningHot()) {
                return $candidate;
            }

            return static::manifestKey($candidate, $file);
        }

        return null;
    }

    // Сборка записывает файл под реальным путём: у пакета, подключённого ссылкой на
    // каталог, это путь за пределами проекта, а не через vendor.
    protected static function manifestKey(string $candidate, string $file): ?string
    {
        $manifest = static::manifest();

        if (isset($manifest[$candidate])) {
            return $candidate;
        }

        $real = realpath(base_path($candidate));

        foreach (array_keys($manifest) as $key) {
            if (str_ends_with($key, '/'.$file) && realpath(base_path($key)) === $real) {
                return $key;
            }
        }

        return null;
    }

    protected static function manifest(): array
    {
        if (static::$manifest !== null) {
            return static::$manifest;
        }

        $path = public_path('build/manifest.json');
        $manifest = is_file($path) ? json_decode(file_get_contents($path), true) : null;

        return static::$manifest = is_array($manifest) ? $manifest : [];
    }
}
