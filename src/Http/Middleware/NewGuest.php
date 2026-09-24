<?php

namespace Posio\CabinetKit\Http\Middleware;

use Closure;
use Inertia\Inertia;
use Posio\CabinetKit\Support\CabinetRedirects;
use Illuminate\Http\Request;

class NewGuest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->user())
            return Inertia::location( CabinetRedirects::url('home') );

        return $next($request);
    }
}
