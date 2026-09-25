<?php

namespace Posio\CabinetKit\Http\Controllers\Auth;

use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Posio\CabinetKit\Services\RegistrationApprovalService;
use Posio\CabinetKit\Support\CabinetRedirects;

class VerificationController extends Controller
{
    // Секунды между повторными отправками письма подтверждения почты.
    const RESEND_COOLDOWN = 120;

    public function notice(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect(CabinetRedirects::intended('home'));
        }

        return Inertia::render('pages/Auth/VerifyEmail', [
            'status' => session('status'),
            'resend_cooldown' => static::resendCooldown($request),
        ]);
    }

    // Пауза перед повторной отправкой письма — только после реальной отправки; при простом входе
    // неподтверждённого пользователя письмо можно запросить сразу.
    public static function rememberSent(Request $request): void
    {
        $request->session()->put('verification_sent_at', now()->timestamp);
    }

    protected static function resendCooldown(Request $request): int
    {
        $sent_at = (int) $request->session()->get('verification_sent_at', 0);

        if (! $sent_at) {
            return 0;
        }

        return max(0, static::RESEND_COOLDOWN - (now()->timestamp - $sent_at));
    }

    public function verify(Request $request, string $id, string $hash)
    {
        // Подпись URL (middleware 'signed') удостоверяет только ссылку, а не того, кто её открыл.
        $user = config('cabinet-kit.user_model')::query()->find($id);

        // Подпись верна, но адресат ссылке больше не соответствует: учётную запись удалили
        // либо почту сменили после отправки письма. Для получателя это протухшее письмо, а не
        // ошибка доступа, — ведём на вход с объяснением вместо голого кода ответа.
        if (! $user || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect()->route('login')->with('status', 'verification-link-invalid');
        }

        // Почту подтверждает только вошедший в эту же учётную запись: пересланное письмо или
        // ссылка, открытая в браузере с чужим входом, подтверждать аккаунт не должны.
        // Гостя ведём на вход, после него та же ссылка откроется снова и подтвердит почту.
        if (! Auth::check()) {
            if ($user->hasVerifiedEmail()) {
                return redirect()->route('login')->with([
                    'status' => 'email-already-verified',
                    'email' => (string) $user->email,
                ]);
            }

            session()->put('url.intended', $request->fullUrl());

            return redirect()->route('login')->with([
                'status' => 'verification-sign-in-required',
                'email' => (string) $user->email,
            ]);
        }

        // Вход под другим пользователем: ничего не подтверждаем, предлагаем войти в нужную учётную запись.
        if (Auth::id() !== $user->getKey()) {
            return $user->hasVerifiedEmail()
                ? $this->outcome('email-already-verified', $user)
                : $this->outcome('email-verification-sign-in-required', $user, $request->fullUrl());
        }

        if ($user->hasVerifiedEmail()) {
            return redirect($this->withVerifiedMark(CabinetRedirects::intended('home')));
        }

        // Роль в АККАУНТЕ не назначается до создания/вступления в аккаунт (per-account,
        // team-scoped). А вот СИСТЕМНУЮ роль (System user, системный team) выдаём здесь —
        // сразу после подтверждения почты, чтобы неверифицированные пользователи ролей
        // не имели.
        if ($user->markEmailAsVerified()) {
            $user->assignDefaultSystemRole();
            event(new Verified($user));
        }

        return redirect(CabinetRedirects::url('after_verify'));
    }

    public function send(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect(CabinetRedirects::intended('home'));
        }

        $request->user()->sendEmailVerificationNotification();
        static::rememberSent($request);

        return back()->with('status', 'verification-link-sent');
    }

    protected function withVerifiedMark(string $url): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').'verified=1';
    }

    // Ссылку подтверждения открыли под чужой сессией и выбрали войти в подтверждаемую учётную
    // запись — выходим и открываем вход с уже подставленной почтой; после входа ссылка
    // откроется снова и подтвердит почту.
    public function switchAccount(Request $request)
    {
        $verified_email = (string) $request->session()->pull('verification_switch_email', '');
        $verification_url = (string) $request->session()->pull('verification_switch_url', '');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($verification_url !== '') {
            $request->session()->put('url.intended', $verification_url);
        }

        return redirect()->route('login')->with('email', $verified_email);
    }

    protected function outcome(string $outcome, $verified_user, ?string $verification_url = null)
    {
        // Почта из сессии, а не из запроса: смена аккаунта не должна подставлять на вход произвольный адрес.
        session()->put('verification_switch_email', (string) $verified_user->email);
        // Ссылка из письма — чтобы после входа в нужную учётную запись подтверждение завершилось само.
        session()->put('verification_switch_url', (string) $verification_url);

        return Inertia::render('pages/Auth/EmailVerificationOutcome', [
            'outcome' => $outcome,
            'verified_email' => (string) $verified_user->email,
            'current_email' => (string) Auth::user()->email,
            'home_url' => CabinetRedirects::url('home'),
        ]);
    }
}
