<?php

namespace Posio\CabinetKit\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Posio\CabinetKit\Models\Account;

class AccountRepository
{
    /** A host that had its own accounts table before the package keeps its own model. */
    public static function accountModel(): string
    {
        return config('cabinet-kit.account_model') ?: Account::class;
    }

    protected function accounts(): Builder
    {
        return static::accountModel()::query();
    }

    public function ownAccount($user): ?Model
    {
        return $this->accounts()->where('owner_id', $user->getKey())->first();
    }

    /** Every account the user owns or has been invited into. */
    public function userAccounts($user): Collection
    {
        $owned = $this->accounts()->where('owner_id', $user->getKey())->get();

        $guest = $this->accounts()
            ->join('user_has_accounts', 'user_has_accounts.account_id', '=', 'accounts.id')
            ->where('user_has_accounts.user_id', $user->getKey())
            ->select('accounts.*')
            ->get();

        return $owned->merge($guest)->unique('id')->values();
    }

    public function findAccount(int $id): ?Model
    {
        return $this->accounts()->find($id);
    }
}
