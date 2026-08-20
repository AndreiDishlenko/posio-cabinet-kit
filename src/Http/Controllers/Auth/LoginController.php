<?php

namespace Posio\CabinetKit\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Posio\CabinetKit\Support\CabinetRedirects;

class LoginController extends Controller
{
    public function showLogin()
    {
        // Outcome of whatever sent the visitor back here — a verification link,
        // a finished password reset, an abandoned social sign-in.
        return Inertia::render('pages/Auth/Login', [
            'status' => session('status'),
        ]);
    }

    public function login(Request $request)
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

        $request->session()->regenerate();

        return redirect(CabinetRedirects::url('after_login'));
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(CabinetRedirects::url('after_logout'));
    }
}
