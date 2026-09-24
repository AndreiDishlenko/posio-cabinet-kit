<?php

namespace Posio\CabinetKit\Http\Middleware;

use Closure;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class NewAuth
{

    public function handle(Request $request, Closure $next)
    {
        $this->rememberIntendedPage($request);

        if (!$request->user() )
            return Inertia::location( route('login') );

        return $next($request);
    }

    // Запомненный адрес открывают заново простым переходом: после входа, по кнопке «В кабинет»
    // на сайте, после подтверждения почты. POST-адрес дал бы 405, JSON — сырые данные, выход —
    // выход. Экран подтверждения почты подтверждённого сам уводит на запомненный адрес — петля.
    protected function rememberIntendedPage(Request $request): void
    {
        if ( !$request->isMethod('GET') || $request->expectsJson() || $request->routeIs('verification.notice', 'logout') )
            return;

        Session::put('url.intended', $request->fullUrl());
    }
}
