<?php

namespace Posio\CabinetKit\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Posio\CabinetKit\Models\Account;
use Posio\CabinetKit\Repositories\AccountRepository;
use Spatie\Permission\PermissionRegistrar;

/**
 * Attach to the host's User model. Gives it accounts()/currentAccount() the
 * same way posio.cabinet's User does — SetPermissionTeam and the account
 * switcher both depend on currentAccount() existing.
 */
trait HasAccount
{
    protected ?Account $current_account = null;

    public function ownAccount(): ?Account
    {
        return app(AccountRepository::class)->ownAccount($this);
    }

    public function currentAccount(): ?Account
    {
        if ($this->current_account) {
            return $this->current_account;
        }

        $accessible = $this->accessibleAccounts();

        if ($accessible->isEmpty()) {
            $this->removeSetting('current_account');
            return null;
        }

        $currentId = $this->getSetting('current_account');
        if (! empty($currentId)) {
            $match = $accessible->firstWhere('id', (int) $currentId);
            if ($match) {
                return $this->setCurrentAccount($match);
            }
        }

        $ownAccountId = $this->accounts()->firstWhere('owner_id', $this->getKey())?->id;
        $fallback = $accessible->firstWhere('id', $ownAccountId) ?? $accessible->first();

        return $this->setCurrentAccount($fallback);
    }

    /** Accounts the user holds at least one role in — membership alone isn't enough. */
    public function accessibleAccounts(): Collection
    {
        $userAccounts = $this->accounts();
        $roleAccountIds = $this->roleAccountIds();

        return $userAccounts
            ->filter(fn ($account) => in_array((int) $account->id, $roleAccountIds, true))
            ->values();
    }

    protected function roleAccountIds(): array
    {
        $table = config('permission.table_names.model_has_roles');
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');
        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');

        return DB::table($table)
            ->where($morphKey, $this->getKey())
            ->where('model_type', $this->getMorphClass())
            ->whereNotNull($teamKey)
            ->distinct()
            ->pluck($teamKey)
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Every account the user owns or is a guest of (regardless of role). */
    public function accounts(): Collection
    {
        return app(AccountRepository::class)->userAccounts($this);
    }

    public function setCurrentAccount(Account $account): Account
    {
        $this->setSetting('current_account', $account->id);
        $this->current_account = $account;

        return $account;
    }

    public function isSystem(): bool
    {
        return $this->email === config('cabinet-kit.system_users.sa.email', 'sa@gmail.com')
            || $this->hasSystemRole('SAdmin');
    }

    public function canSystem(string $permission): bool
    {
        if ($this->isSystem()) {
            return true;
        }

        return $this->withPermissionTeam((int) config('cabinet-kit.system_team_id', 0), fn () => $this->can($permission));
    }

    public function hasSystemRole(string $role): bool
    {
        return $this->withPermissionTeam((int) config('cabinet-kit.system_team_id', 0), fn () => $this->hasRole($role));
    }

    public function setSystemRole(string $role): void
    {
        $this->withPermissionTeam((int) config('cabinet-kit.system_team_id', 0), function () use ($role) {
            $this->syncRoles([$role]);
        });
    }

    public function systemPermissionNames(): array
    {
        if ($this->isSystem()) {
            return [];
        }

        return $this->withPermissionTeam((int) config('cabinet-kit.system_team_id', 0), fn () => $this->getAllPermissions()
            ->pluck('name')
            ->all());
    }

    public function accountPermissionNames(): array
    {
        return $this->getAllPermissions()->pluck('name')->all();
    }

    protected function withPermissionTeam(?int $teamId, callable $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($teamId);
        $this->unsetRelation('roles');
        $this->unsetRelation('permissions');

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
            $this->unsetRelation('roles');
            $this->unsetRelation('permissions');
        }
    }
}
