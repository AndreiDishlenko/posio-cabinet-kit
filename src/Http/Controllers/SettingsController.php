<?php

namespace Posio\CabinetKit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Posio\CabinetKit\Services\MenuService;
use Spatie\Permission\Models\Role;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $account = $user->currentAccount();
        $assignableRoles = config('cabinet-kit.roles.assignable_roles', []);

        $members = $account?->members()->map(fn ($member) => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role_id' => $member->roles->first()?->id,
            'role_name' => $member->roles->first()?->name,
            'role' => $member->roles->first()?->name,
            'is_owner' => (int) $member->id === (int) $account->owner_id,
            'is_system' => method_exists($member, 'isSystem') ? $member->isSystem() : false,
        ])->values() ?? collect();

        $roles = Role::query()
            ->whereIn('name', $assignableRoles)
            ->orderByRaw($this->roleOrderSql($assignableRoles))
            ->get(['id', 'name']);

        return Inertia::render('pages/Settings', [
            'tabs' => app(MenuService::class)->settingsTabsFor($user),
            'account' => $account?->info() ?? [],
            'members' => $members,
            'roles' => $roles,
            'can_manage_account' => $user->can('manage-account'),
            'assignable_roles' => $roles,

            'profile' => $this->profilePayload($user),
            'own_account' => $account?->info() ?? [],
            'account_users' => $members,
            'can_manage_members' => $user->can('manage-members'),
            'can_manage_account_users' => $user->can('manage-account'),
            'is_owner' => $account ? (int) $account->owner_id === (int) $user->getKey() : false,
            'is_system_user' => method_exists($user, 'isSystem') ? $user->isSystem() : false,
            'guest_accounts' => [],
        ]);
    }

    protected function profilePayload($user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? null,
            'avatar' => $user->avatar ?? null,
            'registered' => optional($user->created_at)->format('d.m.Y'),
            'play_notifications' => method_exists($user, 'getSetting')
                ? (bool) $user->getSetting('play_notifications')
                : false,
        ];
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
