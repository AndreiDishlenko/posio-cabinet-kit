<?php

namespace Posio\CabinetKit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $account = $user->currentAccount();
        $assignableRoles = config('cabinet-kit.roles.assignable_roles', []);

        return Inertia::render('pages/CabinetSettings', [
            'profile' => $this->profilePayload($user),
            'own_account' => $account?->info() ?? [],
            'account_users' => $account?->members()->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role_id' => $member->roles->first()?->id,
                'role_name' => $member->roles->first()?->name,
                'is_owner' => (int) $member->id === (int) $account->owner_id,
                'is_system' => method_exists($member, 'isSystem') ? $member->isSystem() : false,
            ])->values() ?? [],
            'assignable_roles' => Role::query()
                ->whereIn('name', $assignableRoles)
                ->orderByRaw($this->roleOrderSql($assignableRoles))
                ->get(['id', 'name']),
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
