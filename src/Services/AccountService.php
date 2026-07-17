<?php

namespace Posio\CabinetKit\Services;

use Illuminate\Support\Facades\DB;
use Posio\CabinetKit\Models\Account;
use Spatie\Permission\PermissionRegistrar;

class AccountService
{
    /** Every role write must happen inside the target account's team scope. */
    protected function scopeRolesToAccount(int $accountId): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($accountId);
    }

    public function createAccount(string $name, $owner): Account
    {
        $account = Account::create([
            'owner_id' => $owner->getKey(),
            'name' => $name,
        ]);

        $this->scopeRolesToAccount($account->id);
        $owner->assignRole(config('cabinet-kit.roles.owner_role'));

        return $account;
    }

    public function inviteMember($user, Account $account, ?string $role = null): void
    {
        DB::table('user_has_accounts')->updateOrInsert(
            ['user_id' => $user->getKey(), 'account_id' => $account->id],
            ['updated_at' => now(), 'created_at' => now()],
        );

        $this->scopeRolesToAccount($account->id);
        $user->assignRole($role ?? config('cabinet-kit.roles.default_member_role'));
    }

    public function setMemberRole($member, Account $account, string $role): void
    {
        if ($member->getKey() === $account->owner_id) {
            abort(422, 'The account owner role cannot be changed.');
        }

        if (! in_array($role, config('cabinet-kit.roles.assignable_roles'), true)) {
            abort(422, "Role {$role} is not assignable.");
        }

        $this->scopeRolesToAccount($account->id);
        $member->syncRoles([$role]);
    }

    public function removeMember($member, Account $account): void
    {
        if ($member->getKey() === $account->owner_id) {
            abort(422, 'The account owner cannot be removed.');
        }

        $this->scopeRolesToAccount($account->id);
        $member->syncRoles([]);

        DB::table('user_has_accounts')
            ->where('user_id', $member->getKey())
            ->where('account_id', $account->id)
            ->delete();
    }
}
