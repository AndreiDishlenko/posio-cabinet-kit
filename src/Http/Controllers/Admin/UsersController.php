<?php

namespace Posio\CabinetKit\Http\Controllers\Admin;

use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Posio\CabinetKit\Services\RegistrationApprovalService;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('pages/UsersAdmin', [
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

            // Встроенный системный пользователь всегда остаётся суперадминистратором.
            if ($target->isSystem()) {
                abort(422, 'Root account role changes denied.');
            }

            $role = Role::query()->where('is_system', 1)->findOrFail($validated['role_id']);
            if ($role->name === 'SAdmin') {
                abort(422, 'The super administrator role cannot be assigned.');
            }

            $target->setSystemRole($role->name);
        }

        return response()->json($this->userPayload($target));
    }

    // Допуск в кабинет из таблицы «Users» — та же операция, что и по ссылке из письма
    // (RegistrationApprovalService::approve), для случая, когда письмо не дошло/потерялось.
    // Право действовать здесь уже проверено middleware маршрута (sysper-users).
    public function approve(Request $request, RegistrationApprovalService $approvals)
    {
        $userModel = config('cabinet-kit.user_model', \App\Models\User::class);

        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $target = $userModel::query()->findOrFail($validated['id']);

        if ($target->approved_at !== null) {
            abort(422, 'Registration is already approved.');
        }

        $approvals->approve($target, $request->user());

        return response()->json([
            'status'      => 'ok',
            'approved_at' => optional($target->approved_at)->toJSON(),
        ]);
    }

    // Ручное подтверждение почты администратором — когда письмо со ссылкой не дошло.
    // Повторяет переход по ссылке из письма: системная роль выдаётся так же.
    public function verifyEmail(Request $request)
    {
        $userModel = config('cabinet-kit.user_model', \App\Models\User::class);

        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $target = $userModel::query()->findOrFail($validated['id']);

        if ($target->hasVerifiedEmail()) {
            abort(422, 'E-mail is already verified.');
        }

        if ($target->markEmailAsVerified()) {
            $target->assignDefaultSystemRole();
            event(new Verified($target));
        }

        // Системная роль появилась только что — строке списка нужно её показать.
        return response()->json([
            'status'  => 'ok',
            'role_id' => $target->fresh()->roles->first()?->id,
        ]);
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
                // Сама дата подтверждения списку не нужна — только признак.
                DB::raw("{$usersTable}.email_verified_at is not null as email_verified"),
                DB::raw('roles.name as role_name'),
                DB::raw('roles.id as role_id'),
            ])
            ->leftJoin($roleTable, function ($join) use ($usersTable, $roleTable, $teamKey, $morphKey, $systemTeamId, $modelType) {
                $join->on("{$usersTable}.id", '=', "{$roleTable}.{$morphKey}")
                    ->where("{$roleTable}.{$teamKey}", '=', $systemTeamId)
                    ->where("{$roleTable}.model_type", '=', $modelType);
            })
            ->leftJoin('roles', 'roles.id', '=', "{$roleTable}.role_id")
            // Встроенный суперадминистратор в управлении пользователями не участвует.
            ->where("{$usersTable}.email", '!=', config('cabinet-kit.system_users.sa.email', 'sa@gmail.com'))
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

        if (Schema::hasColumn($usersTable, 'approval_requested_at')) {
            $query->addSelect("{$usersTable}.approval_requested_at", "{$usersTable}.approved_at");
        }

        $users = $query->get();
        $accountNames = $this->accountNames($users->pluck('id')->all());

        return $users->map(function ($user) use ($accountNames) {
            $user->registered = $user->created_at ? date('d.m.Y', strtotime($user->created_at)) : null;
            $user->email_verified = (int) $user->email_verified;
            $user->account_names = $accountNames[$user->id] ?? '';

            return $user;
        });
    }

    // Компании пользователя одной строкой — список ищет человека и по названию компании,
    // а он бывает и владельцем своей, и участником чужих.
    protected function accountNames(array $userIds): array
    {
        if (! $userIds) {
            return [];
        }

        $owned = DB::table('accounts')
            ->whereIn('owner_id', $userIds)
            ->get(['owner_id as user_id', 'name']);

        $joined = DB::table('user_has_accounts')
            ->join('accounts', 'accounts.id', '=', 'user_has_accounts.account_id')
            ->whereIn('user_has_accounts.user_id', $userIds)
            ->get(['user_has_accounts.user_id', 'accounts.name']);

        return $owned->concat($joined)
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('name')->filter()->unique()->sort()->implode(', '))
            ->all();
    }

    protected function userPayload($user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? null,
            'registered' => optional($user->created_at)->format('d.m.Y'),
            'role_id' => $user->roles->first()?->id,
            'role_name' => $user->roles->first()?->name,
        ];
    }
}
