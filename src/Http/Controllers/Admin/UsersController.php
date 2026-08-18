<?php

namespace Posio\CabinetKit\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('pages/Admin/Users', [
            'users' => $this->users($request),
            'roles' => Role::query()
                ->where('is_system', 1)
                ->whereIn('name', ['System administrator', 'System user'])
                ->orderBy('name')
                ->get(['id', 'name']),
            'permissions' => [
                'users' => $request->user()->canSystem('sysper-users'),
                'roles' => $request->user()->canSystem('sysper-roles'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $userModel = config('cabinet-kit.user_model', \App\Models\User::class);
        $usersTable = config('cabinet-kit.users_table', 'users');

        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'password' => ['nullable', 'confirmed', Password::default()],
        ]);

        $target = $userModel::query()->findOrFail($validated['id']);

        if ($target->isSystem() && ! $request->user()->isSystem()) {
            abort(422, 'Root account changes denied.');
        }

        $updates = ['name' => $validated['name']];
        if (Schema::hasColumn($usersTable, 'phone')) {
            $updates['phone'] = $validated['phone'] ?? null;
        }
        if (! empty($validated['password'])) {
            $updates['password'] = Hash::make($validated['password']);
        }

        $target->forceFill($updates)->save();

        if ($request->filled('role_id')) {
            if (! $request->user()->canSystem('sysper-roles')) {
                abort(403);
            }

            $role = Role::query()->where('is_system', 1)->findOrFail($validated['role_id']);
            if ($role->name === 'SAdmin') {
                abort(422, 'The super administrator role cannot be assigned.');
            }

            $target->setSystemRole($role->name);
        }

        return back();
    }

    protected function users(Request $request)
    {
        $usersTable = config('cabinet-kit.users_table', 'users');
        $systemTeamId = (int) config('cabinet-kit.system_team_id', 0);
        $roleTable = config('permission.table_names.model_has_roles', 'model_has_roles');
        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');
        $userModel = config('cabinet-kit.user_model', \App\Models\User::class);
        $modelType = (new $userModel())->getMorphClass();

        $query = DB::table($usersTable)
            ->select([
                "{$usersTable}.id",
                "{$usersTable}.name",
                "{$usersTable}.email",
                "{$usersTable}.created_at",
                DB::raw('roles.name as role_name'),
                DB::raw('roles.id as role_id'),
            ])
            ->leftJoin($roleTable, function ($join) use ($usersTable, $roleTable, $teamKey, $morphKey, $systemTeamId, $modelType) {
                $join->on("{$usersTable}.id", '=', "{$roleTable}.{$morphKey}")
                    ->where("{$roleTable}.{$teamKey}", '=', $systemTeamId)
                    ->where("{$roleTable}.model_type", '=', $modelType);
            })
            ->leftJoin('roles', 'roles.id', '=', "{$roleTable}.role_id")
            ->orderByDesc("{$usersTable}.created_at");

        if (Schema::hasColumn($usersTable, 'phone')) {
            $query->addSelect("{$usersTable}.phone");
        }

        if (Schema::hasColumn($usersTable, 'is_published')) {
            $query->addSelect("{$usersTable}.is_published");
        }

        if (Schema::hasColumn($usersTable, 'is_finished')) {
            $query->addSelect("{$usersTable}.is_finished");
        }

        return $query->get();
    }
}
