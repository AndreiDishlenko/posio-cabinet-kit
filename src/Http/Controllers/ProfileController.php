<?php

namespace Posio\CabinetKit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $usersTable = config('cabinet-kit.users_table', 'users');
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:{$usersTable},email,{$user->getKey()}",
            'phone' => 'nullable|string|max:40',
            'old_password' => 'nullable|current_password',
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $updates = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (Schema::hasColumn($usersTable, 'phone')) {
            $updates['phone'] = $validated['phone'] ?? null;
        }

        if (! empty($validated['password'])) {
            $updates['password'] = Hash::make($validated['password']);
        }

        $user->forceFill($updates)->save();

        return back();
    }

    public function avatar(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:2048', 'dimensions:min_width=150,min_height=150,max_width=1000,max_height=1000'],
        ]);

        $user = $request->user();
        $path = $request->file('photo')->store('avatars', 'public');

        if (Schema::hasColumn(config('cabinet-kit.users_table', 'users'), 'avatar')) {
            $user->forceFill(['avatar' => $path])->save();
        }

        return back();
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return back();
    }
}
