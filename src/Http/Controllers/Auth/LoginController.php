<?php

namespace Posio\CabinetKit\Http\Controllers\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

        $this->ensureNotLockedOut($request);

        if (! Auth::attempt($credentials, $remember)) {
            $this->countFailedAttempt($request);

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

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

    // Подбор пароля: после серии неудач подряд вход по этой почте с этого адреса
    // замирает на время, а не отвечает бесконечно «неверный пароль».
    protected function ensureNotLockedOut(Request $request): void
    {
        $maxAttempts = $this->maxAttempts();

        if ($maxAttempts === 0 || ! RateLimiter::tooManyAttempts($this->throttleKey($request), $maxAttempts)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => __('cabinet-kit::auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    protected function countFailedAttempt(Request $request): void
    {
        if ($this->maxAttempts() === 0) {
            return;
        }

        RateLimiter::hit($this->throttleKey($request), (int) config('cabinet-kit.login.decay_seconds', 60));
    }

    // Ноль снимает ограничение.
    protected function maxAttempts(): int
    {
        return max(0, (int) config('cabinet-kit.login.max_attempts', 5));
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Адрес после выхода может лежать вне приложения кабинета (вход публичного сайта):
        // обычный редирект Inertia открыл бы его внутри оболочки кабинета.
        if (config('cabinet-kit.logout.full_reload', false)) {
            return Inertia::location(CabinetRedirects::url('after_logout'));
        }

        return redirect(CabinetRedirects::url('after_logout'));
    }
}
