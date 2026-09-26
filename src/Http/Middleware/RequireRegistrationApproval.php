<?php

namespace Posio\CabinetKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Posio\CabinetKit\Services\RegistrationApprovalService;

// Кабинет закрыт, пока администратор не одобрил регистрацию. Ставится после проверки
// подтверждения почты: неподтверждённый пользователь сначала попадает на её экран.
class RequireRegistrationApproval
{
    public function __construct(
        protected RegistrationApprovalService $approvals,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        if (! $this->approvals->isPending($request->user())) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            // Клиент API хоста уводит на вход по адресу из ответа — если хост его ждёт.
            return response()->json(array_filter([
                'message' => __('cabinet-kit::auth.pending_approval'),
                'redirect' => config('cabinet-kit.registration_approval.json_redirect', false) ? route('login') : null,
            ]), 401);
        }

        return redirect()->route('login')->with('status', 'registration-pending-approval');
    }
}
