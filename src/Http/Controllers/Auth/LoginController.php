<?php

namespace Posio\CabinetKit\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Posio\CabinetKit\Services\RegistrationApprovalService;
use Posio\CabinetKit\Support\CabinetRedirects;

class LoginController extends Controller
{
    public function showLogin()
    {
        // Outcome of whatever sent the visitor back here — a verification link,
        // a finished password reset, an abandoned social sign-in.
        return Inertia::render('pages/Auth/Login', [
            'status' => session('status'),
            // Почта, под которой предлагают войти (например после смены аккаунта при подтверждении).
            'email' => session('email'),
        ]);
    }

    public function login(Request $request, RegistrationApprovalService $approvals)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $remember = (bool) $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        // Неподтверждённого пускаем — ему нужен экран подтверждения почты; подтверждённому
        // без одобрения администратора входить некуда.
        if (Auth::user()->hasVerifiedEmail() && $approvals->isPending(Auth::user())) {
            Auth::guard('web')->logout();
            $request->session()->regenerate();

            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => __('cabinet-kit::auth.pending_approval'),
            ]);
        }

        $request->session()->regenerate();

        // Адрес, с которого отправили на вход (ссылка одобрения из письма), важнее стартовой страницы.
        return redirect(CabinetRedirects::intended('after_login'));
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(CabinetRedirects::url('after_logout'));
    }
}
