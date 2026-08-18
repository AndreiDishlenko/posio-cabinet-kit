<?php

namespace Posio\CabinetKit\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsController extends Controller
{
    public function system(Request $request)
    {
        return Inertia::render('pages/Permissions', $this->matrix(true, 'SAdmin'));
    }

    public function account(Request $request)
    {
        return Inertia::render('pages/PermissionsAccount', $this->matrix(false, config('cabinet-kit.roles.owner_role')));
    }

    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
            'granted' => ['required', 'boolean'],
        ]);

        $role = Role::findOrFail($validated['role_id']);
        if (in_array($role->name, ['SAdmin', config('cabinet-kit.roles.owner_role')], true)) {
            abort(422, 'This role can not be modified.');
        }

        if ($role->is_system && ! $request->user()->isSystem()) {
            abort(403, 'System roles can be modified by the super administrator only.');
        }

        $permission = Permission::findOrFail($validated['permission_id']);

        $validated['granted']
            ? $role->givePermissionTo($permission)
            : $role->revokePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['ok' => true]);
    }

    public function store(Request $request)
    {
        if (! $request->user()->isSystem()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:permissions,name'],
            'is_system' => ['sometimes', 'boolean'],
        ]);

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'is_system' => $validated['is_system'] ?? true,
        ]);

        Role::query()->where('name', 'SAdmin')->first()?->givePermissionTo($permission);
        if ($permission->name === 'sysper-log-view') {
            Role::query()->where('name', 'System administrator')->first()?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'permission' => $permission,
            'super_admin_role_id' => (int) (Role::query()->where('name', 'SAdmin')->value('id') ?? 0),
        ]);
    }

    public function rename(Request $request)
    {
        if (! $request->user()->isSystem()) {
            abort(403);
        }

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:permissions,id'],
            'name' => ['required', 'string', 'max:80', 'unique:permissions,name,'.$request->id],
        ]);

        Permission::query()->whereKey($validated['id'])->update(['name' => $validated['name']]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'permission' => Permission::query()->find($validated['id']),
        ]);
    }

    protected function matrix(bool $isSystem, ?string $protectedRole): array
    {
        $permissions = Permission::query()
            ->where('is_system', $isSystem)
            ->orderBy('name')
            ->get(['id', 'name']);

        $allowedIds = $permissions->pluck('id');

        $roles = Role::query()
            ->with('permissions:id,name')
            ->where('is_system', $isSystem)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permission_ids' => $role->permissions->pluck('id')->intersect($allowedIds)->values(),
            ])
            ->values();

        return [
            'roles' => $roles,
            'permissions' => $permissions,
            'protected_role_id' => $protectedRole
                ? (int) (Role::query()->where('name', $protectedRole)->value('id') ?? 0)
                : 0,
        ];
    }
}
