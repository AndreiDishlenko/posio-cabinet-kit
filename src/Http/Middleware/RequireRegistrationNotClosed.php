<?php

namespace Posio\CabinetKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Posio\CabinetKit\Support\RegistrationMode;

// При закрытой регистрации её формы и всё, что может завести учётку (вход через
// провайдеров, ссылка одобрения), отвечают 404. Маршруты остаются зарегистрированными,
// чтобы страницы по-прежнему могли строить их адреса.
class RequireRegistrationNotClosed
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(RegistrationMode::allowsSignUp(), 404);

        return $next($request);
    }
}
