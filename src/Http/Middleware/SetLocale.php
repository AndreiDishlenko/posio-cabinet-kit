<?php

namespace Posio\CabinetKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Posio\CabinetKit\Services\LocaleService;
use Symfony\Component\HttpFoundation\Response;

// Язык публичной страницы задаёт её адрес: языковой префикс, а для сайта с основным
// языком без префикса — отсутствие префикса. Выбор закрепляется в сессии и куке.
class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $fallback_locale = null): Response
    {
		LocaleService::renewLocale($request, $fallback_locale);

        return $next($request);
    }
}
