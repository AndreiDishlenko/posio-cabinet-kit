<?php

namespace Posio\CabinetKit\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Posio\CabinetKit\Services\RegistrationApprovalService;
use Posio\CabinetKit\Support\CabinetRedirects;

// Одобрение регистрации по ссылке из письма администратору. Подпись удостоверяет ссылку,
// но одобрять вправе только вошедший администратор с правом управления пользователями.
class RegistrationApprovalController extends Controller
{
    public function __construct(
        protected RegistrationApprovalService $approvals,
    ) {}

    public function approve(Request $request, string $id, string $hash)
    {
        abort_unless($request->user()->canSystem(RegistrationApprovalService::APPROVER_PERMISSION), 403);

        $user = config('cabinet-kit.user_model')::query()->find($id);

        // Пользователя удалили или сменили ему почту после отправки письма.
        if (! $user || ! $this->approvals->matchesLink($user, $hash)) {
            return $this->outcome('registration-approval-link-invalid');
        }

        if ($user->approved_at !== null) {
            return $this->outcome('registration-already-approved', $user);
        }

        $this->approvals->approve($user, $request->user());

        return $this->outcome('registration-approved', $user);
    }

    protected function outcome(string $outcome, $user = null)
    {
        return Inertia::render('pages/Auth/RegistrationApproval', [
            'outcome' => $outcome,
            'user_name' => (string) ($user?->name ?? ''),
            'user_email' => (string) ($user?->email ?? ''),
            'home_url' => CabinetRedirects::url('home'),
        ]);
    }
}
