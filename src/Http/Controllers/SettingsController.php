<?php

namespace Posio\AdminKit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Posio\AdminKit\Services\MenuService;

class SettingsController extends Controller
{
    public function index(Request $request, MenuService $menuService)
    {
        $user = $request->user();
        $account = $user->currentAccount();

        return Inertia::render('pages/Settings', [
            'tabs' => $menuService->settingsTabsFor($user),
            'account' => $account?->info(),
            'members' => $account?->members()->map->only(['id', 'name', 'email', 'is_owner']),
            'can_manage_account' => $user->can('manage-account'),
        ]);
    }
}
