<?php

namespace Posio\CabinetKit\Http\Controllers\Auth;

use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Posio\CabinetKit\Services\AccountService;

class RegisterController extends Controller
{
    public function __construct(protected AccountService $accountService)
    {
    }

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
            'company_name' => 'required|string|max:255',
        ]);

        $userModel = config('cabinet-kit.user_model');

        $user = $userModel::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        event(new Registered($user));

        $this->accountService->createAccount($validated['company_name'], $user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route(config('cabinet-kit.login_redirect_route', 'cabinet-kit.dashboard'));
    }
}
