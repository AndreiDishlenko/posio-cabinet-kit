<?php

namespace Posio\CabinetKit\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Posio\CabinetKit\Services\AccountService;
use Spatie\Permission\PermissionRegistrar;

class CabinetKitSystemUsersSeeder extends Seeder
{
    public function run(): void
    {
        $userModel = config('cabinet-kit.user_model', \App\Models\User::class);
        $accountService = app(AccountService::class);

        foreach (config('cabinet-kit.system_users', []) as $userConfig) {
            $user = $userModel::query()->where('email', $userConfig['email'])->first();

            if (! $user) {
                $user = new $userModel();
                $user->forceFill($this->newUserAttributes($userConfig))->save();
            }

            $account = $user->ownAccount();
            if (! $account) {
                $account = $accountService->createAccount($userConfig['account_name'], $user);
            }

            app(PermissionRegistrar::class)->setPermissionsTeamId((int) config('cabinet-kit.system_team_id', 0));
            $user->assignRole($userConfig['system_role']);

            app(PermissionRegistrar::class)->setPermissionsTeamId($account->id);
            $user->assignRole(config('cabinet-kit.roles.owner_role'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function newUserAttributes(array $userConfig): array
    {
        $attributes = [
            'name' => $userConfig['name'],
            'email' => $userConfig['email'],
            'password' => Hash::make($userConfig['password']),
        ];

        if ($this->hasColumn('email_verified_at')) {
            $attributes['email_verified_at'] = now();
        }

        if ($this->hasColumn('is_finished')) {
            $attributes['is_finished'] = 1;
        }

        return $attributes;
    }

    protected function hasColumn(string $column): bool
    {
        return Schema::hasColumn(config('cabinet-kit.users_table', 'users'), $column);
    }
}
