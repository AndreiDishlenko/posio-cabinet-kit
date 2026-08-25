<?php

if ( !function_exists('loc_route') ) {
    /**
     * URL страницы в нужной локали. Многоязычный сайт регистрирует маршруты
     * как `{base}.{locale}` — тогда берётся локализованное имя; одноязычный
     * оставляет базовое, и работает оно же. Хост, у которого свой помощник с
     * таким именем, оставляет свой: определение здесь условное.
     */
    function loc_route(string $baseName, ?string $locale = null, array $params = []): string {
        $locale = $locale ?: app()->getLocale();

        if ( \Illuminate\Support\Facades\Route::has("$baseName.$locale") )
            return route("$baseName.$locale", $params);

        return route($baseName, $params);
    }
}
