<?php

namespace Posio\CabinetKit\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Posio\CabinetKit\Notifications\RegistrationApprovalRequest;
use Posio\CabinetKit\Notifications\RegistrationApproved;

// Одобрение самостоятельной регистрации администратором платформы: пока его нет,
// пользователь может подтвердить почту, но в кабинет не входит.
class RegistrationApprovalService
{
    // Системное право, дающее голос за допуск нового пользователя.
    public const APPROVER_PERMISSION = 'sysper-users';

    public function enabled(): bool
    {
        return (bool) config('cabinet_onboarding.registration_approval', false);
    }

    // Выключение режима снимает ожидание со всех, кто не успел дождаться одобрения.
    // Сырые атрибуты: до наката миграции колонок нет, а строгая модель падает на чтении отсутствующих.
    public function isPending($user): bool
    {
        if ($user === null || ! $this->enabled()) {
            return false;
        }

        $attributes = $user->getAttributes();

        return ($attributes['approval_requested_at'] ?? null) !== null
            && ($attributes['approved_at'] ?? null) === null;
    }

    // Ставит новичка в ожидание и зовёт администраторов; вне режима одобрения ничего не делает.
    public function requestApproval($user): void
    {
        if (! $this->enabled()) {
            return;
        }

        $user->forceFill(['approval_requested_at' => now()])->save();

        $approvers = $this->approvers();

        // Без адресатов ссылку одобрения получить некому — пользователь застрянет в ожидании.
        if ($approvers->isEmpty()) {
            Log::error('CabinetKit registration approval: nobody holds '.self::APPROVER_PERMISSION, ['user_id' => $user->getKey()]);

            return;
        }

        $url = $this->approvalUrl($user);

        foreach ($approvers as $approver) {
            // Письмо одного администратора не должно сорвать регистрацию и письма остальным.
            try {
                $approver->notify((new RegistrationApprovalRequest($user, $url))->locale($this->localeOf($approver)));
            } catch (\Throwable $e) {
                Log::error('CabinetKit registration approval request failed: '.get_class($e).': '.$e->getMessage(), ['approver_id' => $approver->getKey()]);
            }
        }
    }

    public function approve($user, $approver): void
    {
        $user->forceFill([
            'approved_at' => now(),
            'approved_by' => $approver->getKey(),
        ])->save();

        try {
            $user->notify((new RegistrationApproved)->locale($this->localeOf($user)));
        } catch (\Throwable $e) {
            Log::error('CabinetKit registration approved notice failed: '.get_class($e).': '.$e->getMessage(), ['user_id' => $user->getKey()]);
        }
    }

    // Бессрочная подписанная ссылка; хэш почты гасит её, если адрес пользователя сменился.
    public function approvalUrl($user): string
    {
        return URL::signedRoute('registration.approve', [
            'id' => $user->getKey(),
            'hash' => sha1((string) $user->email),
        ]);
    }

    public function matchesLink($user, string $hash): bool
    {
        return hash_equals($hash, sha1((string) $user->email));
    }

    // Все, кто держит право одобрения на системном уровне: через роль или напрямую, а также
    // супер-администраторы, которым системные права не расписываются поштучно.
    public function approvers(): Collection
    {
        $model = config('cabinet-kit.user_model');
        $tables = config('permission.table_names');
        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');
        $teamId = (int) config('cabinet-kit.system_team_id', 0);
        $morphType = (new $model)->getMorphClass();

        $permissionIds = DB::table($tables['permissions'])->where('name', self::APPROVER_PERMISSION)->pluck('id');
        $superRoleIds = DB::table($tables['roles'])->where('name', 'SAdmin')->pluck('id');

        $viaRoles = DB::table($tables['model_has_roles'].' as mr')
            ->leftJoin($tables['role_has_permissions'].' as rp', 'rp.role_id', '=', 'mr.role_id')
            ->where('mr.'.$teamKey, $teamId)
            ->where('mr.model_type', $morphType)
            ->where(fn ($query) => $query->whereIn('rp.permission_id', $permissionIds)->orWhereIn('mr.role_id', $superRoleIds))
            ->pluck('mr.'.$morphKey);

        $direct = DB::table($tables['model_has_permissions'])
            ->whereIn('permission_id', $permissionIds)
            ->where($teamKey, $teamId)
            ->where('model_type', $morphType)
            ->pluck($morphKey);

        return $model::query()
            ->where(fn ($query) => $query
                ->whereIn((new $model)->getKeyName(), $viaRoles->merge($direct)->unique()->values())
                ->orWhere('email', config('cabinet-kit.system_users.sa.email', 'sa@gmail.com')))
            ->get()
            ->filter(fn ($user) => $user->canSystem(self::APPROVER_PERMISSION))
            ->values();
    }

    protected function localeOf($user): string
    {
        $locale = method_exists($user, 'getSetting') ? $user->getSetting('locale') : null;

        return is_string($locale) && $locale !== '' ? $locale : app()->getLocale();
    }
}
