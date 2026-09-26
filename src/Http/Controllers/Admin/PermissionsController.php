<?php

namespace Posio\CabinetKit\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Posio\CabinetKit\Http\Controllers\Concerns\RefusesApiRequests;
use Posio\CabinetKit\Support\CabinetKitRoles;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsController extends Controller
{
    use RefusesApiRequests;

    public function system(Request $request)
    {
        return Inertia::render('pages/Permissions', $this->matrix(true, CabinetKitRoles::SUPER_ADMIN_ROLE));
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
        if (in_array($role->name, [CabinetKitRoles::SUPER_ADMIN_ROLE, config('cabinet-kit.roles.owner_role')], true)) {
            return $this->refuse(422, 'This role can not be modified.');
        }

        if ($role->is_system && ! $request->user()->isSystem()) {
            return $this->refuse(403, 'System roles can be modified by the super administrator only.');
        }

        $permission = Permission::findOrFail($validated['permission_id']);

        $validated['granted']
            ? $role->givePermissionTo($permission)
            : $role->revokePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['ok' => true, 'status' => 'ok']);
    }

    public function store(Request $request)
    {
        if (! $request->user()->isSystem()) {
            return $this->refuse(403, 'Only the super administrator can add permissions.');
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

        $superAdmin = Role::query()->where('name', CabinetKitRoles::SUPER_ADMIN_ROLE)->first();
        $superAdmin?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'status' => 'ok',
            'permission' => $permission,
            'super_admin_role_id' => (int) ($superAdmin?->id ?? 0),
        ]);
    }

    public function rename(Request $request)
    {
        if (! $request->user()->isSystem()) {
            return $this->refuse(403, 'Only the super administrator can rename permissions.');
        }

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:permissions,id'],
            'name' => ['required', 'string', 'max:80', 'unique:permissions,name,'.$request->id],
        ]);

        Permission::query()->whereKey($validated['id'])->update(['name' => $validated['name']]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'status' => 'ok',
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
