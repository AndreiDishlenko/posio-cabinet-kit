<?php

namespace Posio\CabinetKit\Http\Controllers\Auth;

use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Posio\CabinetKit\Support\CabinetRedirects;

// Registration ends with the user alone: every step after it is switched by the
// onboarding config, and the package ships with all of them off.
class RegisterController extends Controller
{
    public function showRegister()
    {
        return Inertia::render('pages/Auth/Register');
    }

    public function register(Request $request)
    {
        $usersTable = config('cabinet-kit.users_table', 'users');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:{$usersTable},email",
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $userModel = config('cabinet-kit.user_model');

        $user = $userModel::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect(CabinetRedirects::url('after_register'));
    }
}
