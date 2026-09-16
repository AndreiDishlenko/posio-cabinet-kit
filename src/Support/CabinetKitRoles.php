<?php

namespace Posio\CabinetKit\Support;

use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Роли и права, без которых не работает кабинет.
 *
 * Системный уровень — один в один с posio.cabinet (его основной сидер и
 * миграции прав). Аккаунтный — только то, что пакет сам назначает и проверяет:
 * продуктовые права кассы, заказов и отчётов сюда не переносятся.
 *
 * Сверка запускается после каждого наката миграций, поэтому обновление пакета
 * доводит базу хоста до эталона без ручного запуска сидеров.
 */
class CabinetKitRoles
{
    public const SYSTEM_ROLES = ['SAdmin', 'System administrator', 'System user'];

    public const SYSTEM_PERMISSIONS = [
        'sysper-site',
        'sysper-pages',
        'sysper-users',
        'sysper-roles',
        'sysper-accounts',
        'sysper-usercontent',
        'sysper-log-view',
        'sysper-platform-analytics',
    ];

    // Управление ролями и лог приложения остаются только у суперадминистратора, даже системному администратору не делегируются.
    public const UNDELEGABLE_PERMISSIONS = ['sysper-roles', 'sysper-log-view'];

    public const SUPER_ADMIN_ROLE = 'SAdmin';

    public const ACCOUNT_PERMISSIONS = ['manage-members', 'manage-account'];

    // Аккаунтные права эталонных ролей — как у одноимённых ролей в posio.cabinet, в пределах набора пакета.
    protected const ACCOUNT_ROLE_GRANTS = [
        'Account owner' => ['manage-members', 'manage-account'],
        'Manager' => ['manage-members', 'manage-account'],
    ];

    /**
     * Только досоздаёт недостающее. Роль получает эталонное право лишь в момент,
     * когда появляется сама роль или само право, — снятые оператором в матрице
     * ролей галочки обновление не возвращает.
     */
    public static function sync(): void
    {
        if (! static::tablesExist()) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        // Определения ролей глобальны: при активной команде Spatie проставил бы её в запись.
        $registrar->setPermissionsTeamId(null);

        try {
            $permissions = [];
            $createdPermissions = [];

            foreach (static::permissionNames() as $name) {
                $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                $permissions[$name] = $permission;

                if ($permission->wasRecentlyCreated) {
                    $createdPermissions[] = $name;
                }
            }

            foreach (static::roleGrants() as $roleName => $grantNames) {
                $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

                $pending = $role->wasRecentlyCreated
                    ? $grantNames
                    : array_intersect($grantNames, $createdPermissions);

                if ($pending !== []) {
                    $role->givePermissionTo(array_values(array_map(fn ($name) => $permissions[$name], $pending)));
                }
            }

            static::classify();
            static::pinSystemUserRole();
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
            $registrar->forgetCachedPermissions();
        }
    }

    /**
     * Расхождения с эталоном, которые сверка исправила бы: отсутствующие роли и
     * права и неверный уровень (системный/аккаунтный), из-за которого запись не
     * видна в своей матрице.
     *
     * @return string[]
     */
    public static function drift(): array
    {
        if (! static::tablesExist()) {
            return ['permission tables are missing'];
        }

        $problems = [];
        $roles = Role::query()->whereIn('name', array_keys(static::roleGrants()))->get()->keyBy('name');
        $permissions = Permission::query()->whereIn('name', static::permissionNames())->get()->keyBy('name');
        $hasRoleLevel = Schema::hasColumn(static::table('roles'), 'is_system');
        $hasPermissionLevel = Schema::hasColumn(static::table('permissions'), 'is_system');

        foreach (array_keys(static::roleGrants()) as $name) {
            $role = $roles->get($name);
            $isSystem = in_array($name, self::SYSTEM_ROLES, true);

            if (! $role) {
                $problems[] = "role {$name} is missing";
            } elseif ($hasRoleLevel && (bool) $role->is_system !== $isSystem) {
                $problems[] = "role {$name} is not marked as ".($isSystem ? 'system' : 'account');
            }
        }

        foreach (static::permissionNames() as $name) {
            $permission = $permissions->get($name);
            $isSystem = in_array($name, self::SYSTEM_PERMISSIONS, true);

            if (! $permission) {
                $problems[] = "permission {$name} is missing";
            } elseif ($hasPermissionLevel && (bool) $permission->is_system !== $isSystem) {
                $problems[] = "permission {$name} is not marked as ".($isSystem ? 'system' : 'account');
            }
        }

        return $problems;
    }

    /**
     * @return array<string, string[]> роль => эталонные права
     */
    protected static function roleGrants(): array
    {
        $grants = [
            'SAdmin' => static::permissionNames(),
            'System administrator' => array_values(array_diff(self::SYSTEM_PERMISSIONS, self::UNDELEGABLE_PERMISSIONS)),
            'System user' => [],
        ];

        foreach (static::accountRoleNames() as $name) {
            $grants[$name] ??= self::ACCOUNT_ROLE_GRANTS[$name] ?? [];
        }

        return $grants;
    }

    // Роли, которые пакет назначает сам: владельцу при создании аккаунта и участнику при приглашении.
    protected static function accountRoleNames(): array
    {
        return array_values(array_unique(array_filter([
            config('cabinet-kit.roles.owner_role'),
            config('cabinet-kit.roles.default_member_role'),
            ...(array) config('cabinet-kit.roles.assignable_roles', []),
        ])));
    }

    protected static function permissionNames(): array
    {
        return [...self::SYSTEM_PERMISSIONS, ...self::ACCOUNT_PERMISSIONS];
    }

    // Уровень решает, в какой из двух матриц ролей запись видна; оператор его не меняет.
    protected static function classify(): void
    {
        if (Schema::hasColumn(static::table('roles'), 'is_system')) {
            Role::query()->whereIn('name', self::SYSTEM_ROLES)->update(['is_system' => 1]);
            Role::query()->whereIn('name', array_diff(static::accountRoleNames(), self::SYSTEM_ROLES))->update(['is_system' => 0]);
        }

        if (Schema::hasColumn(static::table('permissions'), 'is_system')) {
            Permission::query()->whereIn('name', self::SYSTEM_PERMISSIONS)->update(['is_system' => 1]);
            Permission::query()->whereIn('name', self::ACCOUNT_PERMISSIONS)->update(['is_system' => 0]);
        }
    }

    // Встроенный системный пользователь всегда суперадминистратор, какую бы системную роль ему ни выставили.
    protected static function pinSystemUserRole(): void
    {
        $email = config('cabinet-kit.system_users.sa.email');
        $userModel = config('cabinet-kit.user_model', \App\Models\User::class);

        if (! $email || ! class_exists($userModel) || ! method_exists($userModel, 'setSystemRole')) {
            return;
        }

        $user = $userModel::query()->where('email', $email)->first();

        if ($user && ! $user->hasSystemRole(self::SUPER_ADMIN_ROLE)) {
            $user->setSystemRole(self::SUPER_ADMIN_ROLE);
        }
    }

    protected static function tablesExist(): bool
    {
        return Schema::hasTable(static::table('roles'))
            && Schema::hasTable(static::table('permissions'))
            && Schema::hasTable(static::table('role_has_permissions'));
    }

    protected static function table(string $key): string
    {
        return config("permission.table_names.{$key}", $key);
    }
}
