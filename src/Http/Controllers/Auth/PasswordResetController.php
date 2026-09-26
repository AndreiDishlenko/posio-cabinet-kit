<?php

namespace Posio\CabinetKit\Http\Controllers\Auth;

use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;

class PasswordResetController extends Controller
{
    public function request()
    {
        return Inertia::render('pages/Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function email(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        // По умолчанию ответ одинаков для любой почты — так по форме не выяснить, кто
        // зарегистрирован. Хост, которому важнее подсказать опечатку, включает ошибку поля.
        if ($status !== Password::RESET_LINK_SENT && config('cabinet-kit.password_reset.report_unknown_email', false)) {
            return back()->withErrors(['email' => $this->statusMessage($status)]);
        }

        return back()->with('status', $this->statusMessage($status));
    }

    public function reset(Request $request, string $token)
    {
        // Протухшую ссылку лучше объяснить сразу, чем после заполнения формы.
        if (config('cabinet-kit.password_reset.check_token_before_form', false)
            && ! $this->tokenIsValid((string) $request->query('email', ''), $token)) {
            return Inertia::render('pages/Auth/ResetPassword', [
                'is_expired' => true,
            ]);
        }

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

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => $this->statusMessage($status),
            ]);
        }

        // Почта уезжает на форму входа, чтобы не набирать её второй раз.
        return redirect()->route('login')->with([
            'status' => $this->statusMessage($status),
            'email' => $validated['email'],
        ]);
    }

    protected function tokenIsValid(string $email, string $token): bool
    {
        $table = config('auth.passwords.'.config('auth.defaults.passwords', 'users').'.table', 'password_reset_tokens');
        $record = DB::table($table)->where('email', $email)->first();

        if (! $record || ! Hash::check($token, $record->token)) {
            return false;
        }

        $expire = (int) config('auth.passwords.'.config('auth.defaults.passwords', 'users').'.expire', 60);

        return ! Carbon::parse($record->created_at)->addMinutes($expire)->isPast();
    }

    // Хост без собственных сообщений сброса пароля на текущем языке получает перевод пакета.
    protected function statusMessage(string $status): string
    {
        return Lang::hasForLocale($status) ? __($status) : __('cabinet-kit::'.$status);
    }
}
