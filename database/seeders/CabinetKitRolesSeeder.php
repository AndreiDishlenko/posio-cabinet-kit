<?php

namespace Posio\CabinetKit\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Schema\Blueprint;
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
        $this->ensurePermissionSchema();

        foreach ($this->permissions() as $name => $isSystem) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            if (Schema::hasColumn('permissions', 'is_system')) {
                $permission->forceFill(['is_system' => $isSystem])->save();
            }
        }

        $permission = Permission::where('name', 'manage-account')->first();

        $superAdminRole = Role::firstOrCreate(['name' => 'SAdmin', 'guard_name' => 'web']);
        $systemAdminRole = Role::firstOrCreate(['name' => 'System administrator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'System user', 'guard_name' => 'web']);

        $ownerRole = Role::firstOrCreate(['name' => 'Account owner', 'guard_name' => 'web']);
        $ownerRole->givePermissionTo($permission);

        Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web'])->givePermissionTo($permission);
        Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web'])->givePermissionTo($permission);
        Role::firstOrCreate(['name' => 'User', 'guard_name' => 'web']);

        $superAdminRole->givePermissionTo(Permission::all());
        $systemAdminRole->givePermissionTo(Permission::query()
            ->where('is_system', 1)
            ->where('name', '!=', 'sysper-roles')
            ->get());

        if (Schema::hasColumn('roles', 'is_system')) {
            Role::query()->whereIn('name', ['SAdmin', 'System administrator', 'System user'])->update(['is_system' => 1]);
            Role::query()->whereIn('name', ['Account owner', 'Administrator', 'Manager', 'User'])->update(['is_system' => 0]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function permissions(): array
    {
        $systemPermissions = [
            'sysper-site',
            'sysper-pages',
            'sysper-users',
            'sysper-roles',
            'sysper-accounts',
            'sysper-usercontent',
            'sysper-platform-analytics',
            'sysper-log-view',
        ];

        $accountPermissions = [
            'manage-members',
            'manage-account',
            'manage-cashiers',
            'manage-integrations',
            'manage-orders',
            'manage-docs',
            'view-reports',
            'view-owner-reports',
        ];

        return array_merge(
            array_fill_keys($systemPermissions, true),
            array_fill_keys($accountPermissions, false),
        );
    }

    protected function ensurePermissionSchema(): void
    {
        if (Schema::hasTable('roles') && ! Schema::hasColumn('roles', 'is_system')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->boolean('is_system')->default(0)->after('guard_name');
            });
        }

        if (Schema::hasTable('permissions') && ! Schema::hasColumn('permissions', 'is_system')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->boolean('is_system')->default(1)->after('guard_name');
            });
        }
    }
}
