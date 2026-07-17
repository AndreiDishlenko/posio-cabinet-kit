<?php

namespace Posio\CabinetKit\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role definitions are global (team_id NULL) — only assignments are
 * per-account. Safe to re-run: guard clauses on every firstOrCreate.
 */
class CabinetKitRolesSeeder extends Seeder
{
    public function run(): void
    {
        // Role/permission definitions are global — team scope must be off while
        // creating them, otherwise Spatie stamps the current team id on them.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $permission = Permission::firstOrCreate(['name' => 'manage-account', 'guard_name' => 'web']);

        $ownerRole = Role::firstOrCreate(['name' => 'Account owner', 'guard_name' => 'web']);
        $ownerRole->givePermissionTo($permission);

        Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web'])->givePermissionTo($permission);
        Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'User', 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
