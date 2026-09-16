<?php

namespace Posio\CabinetKit\Http\Controllers\Auth;

use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Posio\CabinetKit\Services\RegistrationApprovalService;

// Registration ends with the user alone plus the mandatory email confirmation;
// every other step after it is switched by the onboarding config, all off here.
class RegisterController extends Controller
{
    public function showRegister()
    {
        return Inertia::render('pages/Auth/Register');
    }

    public function register(Request $request, RegistrationApprovalService $approvals)
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

        // Фреймворк шлёт письмо сам только модели с контрактом подтверждения; у хоста его может не быть.
        if (! $user instanceof MustVerifyEmail) {
            $user->sendEmailVerificationNotification();
        }

        $approvals->requestApproval($user);

        // Сессия нужна экрану подтверждения почты; кабинет до одобрения регистрации закрыт отдельно.
        Auth::login($user);
        $request->session()->regenerate();

        // Почта, введённая руками, ничем не подтверждена — подтверждение обязательно и настройкой не отключается.
        return redirect()->route('verification.notice');
    }
}
