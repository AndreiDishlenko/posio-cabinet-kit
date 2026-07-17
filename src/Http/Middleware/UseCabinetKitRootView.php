<?php

namespace Posio\CabinetKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Points Inertia at CabinetKit's own Blade root view for every CabinetKit
 * route (auth pages included), so the cabinet gets its own Vite entry
 * instead of piggybacking on the host's main app view. The host can swap
 * the whole view via config('cabinet-kit.root_view') — e.g. to its own
 * blade that loads extra assets — without touching this middleware.
 */
class UseCabinetKitRootView
{
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::setRootView(config('cabinet-kit.root_view', 'cabinet-kit::app'));

        return $next($request);
    }
}
