<?php

namespace Posio\CabinetKit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

/**
 * Placeholder landing page — the host is expected to override this route
 * (or the page component via resources/_admin/overrides/pages/Dashboard.vue)
 * with real content. Kept minimal on purpose: CabinetKit is a shell, not a
 * dashboard product.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('pages/Dashboard', [
            'account' => $request->user()->currentAccount()?->info(),
        ]);
    }
}
