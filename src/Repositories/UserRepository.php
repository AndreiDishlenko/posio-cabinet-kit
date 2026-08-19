<?php

namespace Posio\CabinetKit\Repositories;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserRepository
{
    public function findOrCreateGoogleUser($googleUser)
    {
        return $this->findOrCreateSocialUser(
            'google_id',
            $googleUser->getId(),
            $googleUser->getEmail(),
            $googleUser->getName(),
            $googleUser->getAvatar(),
        );
    }

    public function findOrCreateAppleUser($appleUser)
    {
        // Apple hands over an address only on the very first sign-in of a given
        // person; later returns carry the provider id alone.
        return $this->findOrCreateSocialUser(
            'apple_id',
            $appleUser->getId(),
            $appleUser->getEmail(),
            $appleUser->getName(),
            null,
        );
    }

    protected function findOrCreateSocialUser(string $column, string $providerId, ?string $email, ?string $name, ?string $avatar)
    {
        $model = config('cabinet-kit.user_model');

        if ($user = $model::query()->where($column, $providerId)->first()) {
            return $user;
        }

        // Same person arriving through a provider for the first time: attach the
        // provider id to the row they already have instead of opening a second
        // one for the same address.
        if ($email && $user = $model::query()->where('email', $email)->first()) {
            $user->forceFill([$column => $providerId])->save();

            // The provider vouched for the address, so a pending confirmation
            // letter is moot from here on.
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            return $user;
        }

        $attributes = [
            'name' => $this->normalizeName($name, $email),
            'email' => $email,
            $column => $providerId,
            // Social sign-in sets no password, but the column is required: store
            // a random unguessable hash. People set their own through the
            // forgotten-password flow.
            'password' => Hash::make(Str::random(40)),
            'email_verified_at' => now(),
        ];

        if ($avatar && $this->usersTableHas('avatar')) {
            $attributes['avatar'] = $avatar;
        }

        // Written past mass-assignment on purpose: the host owns the user model
        // and rarely lists provider ids among its fillable attributes, and a
        // silently dropped provider id would create a fresh row on every visit.
        $user = new $model;
        $user->forceFill($attributes)->save();

        // Social sign-in never asks for a language — keep the one the visitor
        // was already browsing in.
        if (method_exists($user, 'setSetting')) {
            $user->setSetting('locale', $this->detectLocale());
        }

        return $user;
    }

    // Guest pages carry no signed-in preference, so the language comes from the
    // switcher's own trail (session, then cookie) before the application default.
    protected function detectLocale(): string
    {
        $request = request();

        $locale = ($request->hasSession() ? $request->session()->get('locale') : null)
            ?: $request->cookie('locale');

        $locales = array_map('strval', array_keys(config('cabinet-kit.translations.locales', [])));

        return $locale && in_array($locale, $locales, true) ? $locale : app()->getLocale();
    }

    // Names from a provider can exceed the column's capacity or be missing
    // entirely — trim to fit and fall back to the local part of the address.
    protected function normalizeName(?string $name, ?string $email): string
    {
        $name = trim((string) $name);

        if ($name === '' && $email) {
            $name = Str::before($email, '@');
        }

        return mb_substr($name !== '' ? $name : 'User', 0, 40);
    }

    protected function usersTableHas(string $column): bool
    {
        return Schema::hasColumn(config('cabinet-kit.users_table', 'users'), $column);
    }
}
