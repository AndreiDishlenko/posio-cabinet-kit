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

    /**
     * Реквизиты организации живут в json-мешке, а не отдельными колонками:
     * структурные колонки таблицы принадлежат пакету и могут разойтись
     * с миграциями хоста при обновлении.
     */
    public const PROFILE_FIELDS = [
        'description',
        'address',
        'phone',
        'email',
        'url',
        'logo',
        'unitsystem_id',
        'currency_id',
    ];

    public function info(): array
    {
        return array_merge(
            $this->only(['id', 'name', 'expire']),
            $this->profile(),
        );
    }

    /** Реквизиты организации из json-мешка, всегда полным набором ключей. */
    public function profile(): array
    {
        $settings = (array) $this->settings;

        return collect(self::PROFILE_FIELDS)
            ->mapWithKeys(fn ($field) => [$field => $settings[$field] ?? null])
            ->all();
    }

    /** Записывает реквизиты организации, игнорируя всё, что не входит в набор. */
    public function fillProfile(array $values): void
    {
        $settings = (object) ($this->settings ?? new \stdClass());

        foreach (self::PROFILE_FIELDS as $field) {
            if (array_key_exists($field, $values)) {
                $settings->{$field} = $values[$field];
            }
        }

        $this->settings = $settings;
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
        $usersTable = config('cabinet-kit.users_table', 'users');

        return $this->belongsToMany($userModel, 'user_has_accounts')
            ->select(["{$usersTable}.id", 'name', 'email'])
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
