<?php

namespace Posio\CabinetKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentUser;
use Illuminate\Support\Collection;
use Posio\CabinetKit\Traits\HasCustomFields;

class Account extends Model
{
    use HasCustomFields;

    protected $guarded = [];

    protected $casts = [
        'expire' => 'datetime',
    ];

    protected ?EloquentUser $owner = null;

    public function info(): array
    {
        return $this->only(['id', 'name', 'expire']);
    }

    /** @return EloquentUser the host's own User model instance */
    public function owner(): EloquentUser
    {
        if ($this->owner) {
            return $this->owner;
        }

        $userModel = config('cabinet-kit.user_model');

        return $this->owner = $userModel::query()
            ->select(['id', 'name', 'email'])
            ->findOrFail($this->owner_id);
    }

    /** Users invited into this account (owner is not part of this pivot). */
    public function guestUsers(): Collection
    {
        $userModel = config('cabinet-kit.user_model');

        return $this->belongsToMany($userModel, 'user_has_accounts')
            ->select(['users.id', 'name', 'email'])
            ->get()
            ->makeHidden('pivot');
    }

    /** Owner + guests, owner first, flagged with is_owner. */
    public function members(): Collection
    {
        $members = $this->guestUsers();

        $owner = $this->owner();
        $owner->is_owner = true;

        return $members->prepend($owner);
    }
}
