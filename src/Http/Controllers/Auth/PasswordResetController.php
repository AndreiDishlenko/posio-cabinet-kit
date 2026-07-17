<?php

namespace Posio\CabinetKit\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;

class PasswordResetController extends Controller
{
    public function request()
    {
        return Inertia::render('pages/Auth/ForgotPassword');
    }

    public function email(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return back()->with('status', __($status));
    }

    public function reset(Request $request, string $token)
    {
        return Inertia::render('pages/Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $validated,
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        return redirect()->route('login')->with('status', __($status));
    }
}
