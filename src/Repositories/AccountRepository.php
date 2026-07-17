<?php

namespace Posio\AdminKit\Repositories;

use Illuminate\Support\Collection;
use Posio\AdminKit\Models\Account;

class AccountRepository
{
    public function ownAccount($user): ?Account
    {
        return Account::query()->where('owner_id', $user->getKey())->first();
    }

    /** Every account the user owns or has been invited into. */
    public function userAccounts($user): Collection
    {
        $owned = Account::query()->where('owner_id', $user->getKey())->get();

        $guest = Account::query()
            ->join('user_has_accounts', 'user_has_accounts.account_id', '=', 'accounts.id')
            ->where('user_has_accounts.user_id', $user->getKey())
            ->select('accounts.*')
            ->get();

        return $owned->merge($guest)->unique('id')->values();
    }

    public function findAccount(int $id): ?Account
    {
        return Account::find($id);
    }
}
