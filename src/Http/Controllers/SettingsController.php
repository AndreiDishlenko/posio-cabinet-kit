<?php

namespace Posio\CabinetKit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Posio\CabinetKit\Services\MenuService;
use Spatie\Permission\Models\Role;

class SettingsController extends Controller
{
    public function index(Request $request, MenuService $menuService)
    {
        $user = $request->user();
        $account = $user->currentAccount();
        $assignableRoles = config('cabinet-kit.roles.assignable_roles', []);

        return Inertia::render('pages/Settings', [
            'tabs' => $menuService->settingsTabsFor($user),
            'account' => $account?->info(),
            'members' => $account?->members()->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'is_owner' => (bool) ($member->is_owner ?? false),
                'role' => $member->roles->first()?->name,
            ]),
            'assignable_roles' => Role::query()
                ->whereIn('name', $assignableRoles)
                ->orderByRaw($this->roleOrderSql($assignableRoles))
                ->get(['name'])
                ->map(fn (Role $role) => ['name' => $role->name]),
            'can_manage_account' => $user->can('manage-account'),
        ]);
    }

    protected function roleOrderSql(array $roles): string
    {
        if ($roles === []) {
            return 'name asc';
        }

        $cases = collect($roles)
            ->values()
            ->map(fn ($role, $index) => "when '".str_replace("'", "''", $role)."' then {$index}")
            ->implode(' ');

        return "case name {$cases} else 999 end";
    }
}
