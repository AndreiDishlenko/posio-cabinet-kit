<?php

namespace Posio\CabinetKit\Http\Controllers\Auth;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Posio\CabinetKit\Repositories\UserRepository;
use Posio\CabinetKit\Services\AccountService;

class SocialAuthController extends Controller
{
    public function __construct(
        protected UserRepository $userRepo,
        protected AccountService $accountService,
    ) {}

    public function googleRedirect()
    {
        $this->ensureProviderIsUsable('google');

        return Socialite::driver('google')->redirect();
    }

    public function googleCallback()
    {
        $this->ensureProviderIsUsable('google');

        try {
            $googleUser = Socialite::driver('google')->user();
            $user = $this->userRepo->findOrCreateGoogleUser($googleUser);
        } catch (InvalidStateException) {
            return $this->stateMismatch('google');
        } catch (\Throwable $e) {
            Log::error('CabinetKit google sign-in failed: '.$this->describe($e));

            return $this->backToLogin();
        }

        return $this->signIn($user);
    }

    public function appleRedirect()
    {
        $this->ensureProviderIsUsable('apple');

        return Socialite::driver('apple')->redirect();
    }

    public function appleCallback()
    {
        $this->ensureProviderIsUsable('apple');

        try {
            // Apple posts the return from its own origin, so the session cookie
            // does not ride along and the state check has nothing to compare.
            $appleUser = Socialite::driver('apple')->stateless()->user();
            $user = $this->userRepo->findOrCreateAppleUser($appleUser);
        } catch (InvalidStateException) {
            return $this->stateMismatch('apple');
        } catch (\Throwable $e) {
            Log::error('CabinetKit apple sign-in failed: '.$this->describe($e));

            return $this->backToLogin();
        }

        return $this->signIn($user);
    }

    protected function signIn($user)
    {
        // A social sign-up has no company name to ask for, unlike the form-based
        // one, so the first account is named after its owner and renamed later.
        if ($user->wasRecentlyCreated) {
            $this->accountService->createAccount($this->defaultAccountName($user), $user);
        }

        Auth::login($user, remember: true);

        request()->session()->regenerate();

        // Full page load rather than an Inertia visit: the provider returns the
        // browser here by plain navigation, and the session was just rotated.
        return Inertia::location(route(config('cabinet-kit.login_redirect_route', 'cabinet-kit.users')));
    }

    protected function defaultAccountName($user): string
    {
        $name = trim((string) ($user->name ?? ''));

        if ($name === '' && $user->email) {
            $name = Str::before($user->email, '@');
        }

        return mb_substr($name !== '' ? $name : 'Account', 0, 255);
    }

    // A provider without credentials is a host that never opted into it: hide
    // the endpoint rather than fail deep inside the driver. The routes stay
    // registered regardless so the sign-in page can still resolve their URLs.
    protected function ensureProviderIsUsable(string $provider): void
    {
        abort_unless(class_exists(Socialite::class), 404);
        abort_unless(filled(config("services.{$provider}.client_id")), 404);
    }

    // Return from the provider with no matching session marker: a re-opened
    // return page (the marker is single-use), a stale session, or a sign-in
    // started on another host — the session cookie is host-only and does not
    // travel to a neighbouring domain. This is a routine outcome of signing in
    // rather than a service failure, hence warning: at error level every page
    // refresh would raise a red alert. The details tell a visitor's repeat
    // apart from a genuine loss of session.
    protected function stateMismatch(string $provider)
    {
        $request = request();

        Log::warning("CabinetKit {$provider} sign-in state mismatch", [
            'has_state' => $request->filled('state'),
            'host' => $request->getHost(),
            'referer' => $request->headers->get('referer'),
            'session' => $request->hasSession() ? $request->session()->getId() : null,
        ]);

        return $this->backToLogin();
    }

    // Exception class in the text: some of them (the session marker mismatch
    // first among them) arrive with no message at all, and the alert came out
    // empty — there was no telling from it what had happened.
    protected function describe(\Throwable $e): string
    {
        $message = trim($e->getMessage());

        return get_class($e).($message !== '' ? ': '.$message : '');
    }

    // A failed social sign-in returns to the sign-in page with an explanation —
    // otherwise the visitor lands back where they started without a word about
    // why.
    protected function backToLogin()
    {
        return redirect()->route('login')->with('status', 'social-auth-failed');
    }
}
