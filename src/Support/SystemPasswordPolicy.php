<?php

namespace Posio\CabinetKit\Support;

use Illuminate\Support\Facades\Hash;

/**
 * The accounts the installer seeds sign in with a password that is written in
 * a config file and identical in every project built on this package — public
 * knowledge, in other words. Until such an account replaces it, its sign-in is
 * treated as unfinished, and the cabinet lets it nowhere but the change form.
 *
 * The state lives nowhere but in the password hash itself: no flag, no column,
 * no migration. Setting any other password ends it; setting the config one
 * again brings it back.
 */
class SystemPasswordPolicy
{
    public static function mustChangePassword($user): bool
    {
        if (! config('cabinet-kit.force_system_password_change', true)) {
            return false;
        }

        $seeded = static::seededPasswordFor($user);

        return $seeded !== null && Hash::check($seeded, (string) $user->getAuthPassword());
    }

    /** The password this account was seeded with, or null when it is not a seeded one. */
    public static function seededPasswordFor($user): ?string
    {
        if (! $user || blank($user->email ?? null)) {
            return null;
        }

        foreach ((array) config('cabinet-kit.system_users', []) as $entry) {
            if (! is_array($entry) || blank($entry['email'] ?? null) || blank($entry['password'] ?? null)) {
                continue;
            }

            if (mb_strtolower((string) $entry['email']) === mb_strtolower((string) $user->email)) {
                return (string) $entry['password'];
            }
        }

        return null;
    }

    /** Guards the change form against "changing" the password to the same seeded value. */
    public static function isSeededPassword($user, string $candidate): bool
    {
        $seeded = static::seededPasswordFor($user);

        return $seeded !== null && hash_equals($seeded, $candidate);
    }
}
