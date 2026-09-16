<?php

namespace Posio\CabinetKit\Http\Controllers\Auth;

use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Posio\CabinetKit\Support\CabinetRedirects;

class VerificationController extends Controller
{
    public function notice(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect(CabinetRedirects::url('after_verify'));
        }

        return Inertia::render('pages/Auth/VerifyEmail', [
            'status' => session('status'),
        ]);
    }

    public function verify(EmailVerificationRequest $request)
    {
        // Роль в АККАУНТЕ не назначается до создания/вступления в аккаунт (per-account,
        // team-scoped). А вот СИСТЕМНУЮ роль (System user, системный team) выдаём здесь —
        // сразу после подтверждения почты, чтобы неверифицированные пользователи ролей
        // не имели.
        if (! $request->user()->hasVerifiedEmail() && $request->user()->markEmailAsVerified()) {
            $request->user()->assignDefaultSystemRole();
            event(new Verified($request->user()));
        }

        return redirect(CabinetRedirects::url('after_verify'));
    }

    public function send(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect(CabinetRedirects::url('after_verify'));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
