<?php

namespace Posio\CabinetKit\Http\Controllers\Auth;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class VerificationController extends Controller
{
    public function notice(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route(config('cabinet-kit.login_redirect_route', 'cabinet-kit.dashboard'));
        }

        return Inertia::render('pages/Auth/VerifyEmail');
    }

    public function verify(EmailVerificationRequest $request)
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill();
        }

        return redirect()->route(config('cabinet-kit.login_redirect_route', 'cabinet-kit.dashboard'));
    }

    public function send(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route(config('cabinet-kit.login_redirect_route', 'cabinet-kit.dashboard'));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
