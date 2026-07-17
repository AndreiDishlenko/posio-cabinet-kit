<?php

namespace Posio\CabinetKit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
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
        ]);

        $user->forceFill($validated)->save();

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
