<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

// Лог приложения остаётся только у суперадминистратора. Сверка ролей недостающее
// лишь досоздаёт и выданное раньше не отзывает, поэтому снятие — миграцией.
return new class extends Migration
{
    public function up(): void
    {
        [$roleId, $permissionId] = $this->ids();

        if (! $roleId || ! $permissionId) {
            return;
        }

        DB::table($this->table('role_has_permissions'))
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        [$roleId, $permissionId] = $this->ids();

        if (! $roleId || ! $permissionId) {
            return;
        }

        DB::table($this->table('role_has_permissions'))->insertOrIgnore([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function ids(): array
    {
        if (! Schema::hasTable($this->table('roles')) || ! Schema::hasTable($this->table('permissions'))) {
            return [null, null];
        }

        return [
            DB::table($this->table('roles'))->where('name', 'System administrator')->value('id'),
            DB::table($this->table('permissions'))->where('name', 'sysper-log-view')->value('id'),
        ];
    }

    protected function table(string $key): string
    {
        return config("permission.table_names.{$key}", $key);
    }
};
