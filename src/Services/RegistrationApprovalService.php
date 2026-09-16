<?php

namespace Posio\CabinetKit\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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

        $recipients = $this->recipients();

        // Без адресатов ссылку одобрения получить некому — пользователь застрянет в ожидании.
        if ($recipients->isEmpty()) {
            Log::error('CabinetKit registration approval: no recipients (no emails configured and nobody holds '.self::APPROVER_PERMISSION.')', ['user_id' => $user->getKey()]);

            return;
        }

        $url = $this->approvalUrl($user);

        foreach ($recipients as $recipient) {
            // Письмо одного администратора не должно сорвать регистрацию и письма остальным.
            try {
                $recipient->notify((new RegistrationApprovalRequest($user, $url))->locale($this->localeOf($recipient)));
            } catch (\Throwable $e) {
                Log::error('CabinetKit registration approval request failed: '.get_class($e).': '.$e->getMessage(), ['recipient' => $recipient->email ?? $recipient->routes['mail'] ?? null]);
            }
        }
    }

    // Адреса из настройки, а без неё — все держатели права одобрения. Адрес пользователя системы
    // получает письмо на его языке; одобрить по ссылке всё равно может только вошедший с этим правом.
    public function recipients(): Collection
    {
        $emails = $this->configuredEmails();

        if ($emails === []) {
            return $this->approvers();
        }

        $model = config('cabinet-kit.user_model');
        $users = $model::query()->whereIn('email', $emails)->get()->keyBy(fn ($user) => mb_strtolower((string) $user->email));

        return collect($emails)->map(fn (string $email) => $users->get($email) ?? Notification::route('mail', $email));
    }

    protected function configuredEmails(): array
    {
        $emails = config('cabinet_onboarding.registration_approval_emails', []);

        if (is_string($emails)) {
            $emails = explode(',', $emails);
        }

        return collect($emails)
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
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
