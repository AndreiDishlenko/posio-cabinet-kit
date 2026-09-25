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
    public function __construct(
        protected RegistrationApprovalService $approvals,
    ) {}

    public function notice(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect(CabinetRedirects::intended('home'));
        }

        return Inertia::render('pages/Auth/VerifyEmail', [
            'status' => session('status'),
        ]);
    }

    public function verify(string $id, string $hash)
    {
        // Подлинность ссылки уже подтверждена подписью URL (middleware 'signed').
        // Пользователя определяем из ссылки, а не из сессии, — верификация должна
        // работать на любом устройстве, даже без входа или под другим аккаунтом.
        $user = config('cabinet-kit.user_model')::query()->find($id);

        // Подпись верна, но адресат ссылке больше не соответствует: учётную запись удалили
        // либо почту сменили после отправки письма. Для получателя это протухшее письмо, а не
        // ошибка доступа, — ведём на вход с объяснением вместо голого кода ответа.
        if (! $user || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect()->route('login')->with('status', 'verification-link-invalid');
        }

        // Ссылку открыли там, где вошёл другой пользователь. Вход только для гостей и молча
        // увёл бы его в кабинет — показываем, чья почта подтверждена и под кем вход.
        $signed_in_as_other = Auth::check() && Auth::id() !== $user->getKey();

        if ($user->hasVerifiedEmail()) {
            if ($signed_in_as_other) {
                return $this->outcome('email-already-verified', $user);
            }

            return Auth::id() === $user->getKey()
                ? redirect($this->withVerifiedMark(CabinetRedirects::intended('home')))
                : redirect()->route('login')->with('status', 'email-already-verified');
        }

        // Роль в АККАУНТЕ не назначается до создания/вступления в аккаунт (per-account,
        // team-scoped). А вот СИСТЕМНУЮ роль (System user, системный team) выдаём здесь —
        // сразу после подтверждения почты, чтобы неверифицированные пользователи ролей
        // не имели.
        if ($user->markEmailAsVerified()) {
            $user->assignDefaultSystemRole();
            event(new Verified($user));
        }

        if ($signed_in_as_other) {
            return $this->outcome('email-verified', $user);
        }

        // Если подтверждают на том же устройстве, где залогинен этот пользователь —
        // ведём в кабинет; иначе (другое устройство) — на страницу входа.
        return Auth::id() === $user->getKey()
            ? redirect(CabinetRedirects::url('after_verify'))
            : redirect()->route('login')->with('status', $this->approvals->isPending($user) ? 'registration-pending-approval' : 'email-verified');
    }

    public function send(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect(CabinetRedirects::intended('home'));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }

    protected function withVerifiedMark(string $url): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').'verified=1';
    }

    // Почту подтвердили под чужой сессией и выбрали войти подтверждённой — выходим и
    // открываем вход с уже подставленной подтверждённой почтой.
    public function switchAccount(Request $request)
    {
        $verified_email = (string) $request->session()->pull('verification_switch_email', '');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('email', $verified_email);
    }

    protected function outcome(string $outcome, $verified_user)
    {
        // Почта из сессии, а не из запроса: смена аккаунта не должна подставлять на вход произвольный адрес.
        session()->put('verification_switch_email', (string) $verified_user->email);

        return Inertia::render('pages/Auth/EmailVerificationOutcome', [
            'outcome' => $outcome,
            'verified_email' => (string) $verified_user->email,
            'current_email' => (string) Auth::user()->email,
            'home_url' => CabinetRedirects::url('home'),
        ]);
    }
}
