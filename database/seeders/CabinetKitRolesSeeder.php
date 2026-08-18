<?php

namespace Posio\CabinetKit\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
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

        $superAdminRole = Role::firstOrCreate(['name' => 'SAdmin', 'guard_name' => 'web']);
        $systemAdminRole = Role::firstOrCreate(['name' => 'System administrator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'System user', 'guard_name' => 'web']);

        $ownerRole = Role::firstOrCreate(['name' => 'Account owner', 'guard_name' => 'web']);
        $ownerRole->givePermissionTo($permission);

        Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web'])->givePermissionTo($permission);
        Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'User', 'guard_name' => 'web']);

        $superAdminRole->givePermissionTo(Permission::all());
        $systemAdminRole->givePermissionTo(Permission::query()->where('name', '!=', 'sysper-roles')->get());

        if (Schema::hasColumn('roles', 'is_system')) {
            Role::query()->whereIn('name', ['SAdmin', 'System administrator', 'System user'])->update(['is_system' => 1]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
