<?php

namespace Posio\CabinetKit\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Posio\CabinetKit\Services\AccountService;

class AccountController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected AccountService $accountService)
    {
    }

    /** Switch the authenticated user's active account (account switcher in the burger menu). */
    public function set(Request $request)
    {
        $validated = $request->validate(['account_id' => 'required|integer']);

        $account = $request->user()->accessibleAccounts()->firstWhere('id', $validated['account_id']);

        abort_unless($account, 403, 'You do not have access to this account.');

        $request->user()->setCurrentAccount($account);

        return back()->with('account', $account->info());
    }

    public function inviteMember(Request $request)
    {
        $this->authorize('manage-account');

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:'.config('cabinet-kit.users_table', 'users').',id',
            'role' => 'nullable|string',
        ]);

        $userModel = config('cabinet-kit.user_model');
        $member = $userModel::findOrFail($validated['user_id']);

        $this->accountService->inviteMember($member, $request->user()->currentAccount(), $validated['role'] ?? null);

        return back();
    }

    public function setMemberRole(Request $request)
    {
        $this->authorize('manage-account');

        $validated = $request->validate([
            'user_id' => 'required|integer',
            'role' => 'required|string',
        ]);

        $userModel = config('cabinet-kit.user_model');
        $member = $userModel::findOrFail($validated['user_id']);

        $this->accountService->setMemberRole($member, $request->user()->currentAccount(), $validated['role']);

        return back();
    }

    public function removeMember(Request $request)
    {
        $this->authorize('manage-account');

        $validated = $request->validate(['user_id' => 'required|integer']);

        $userModel = config('cabinet-kit.user_model');
        $member = $userModel::findOrFail($validated['user_id']);

        $this->accountService->removeMember($member, $request->user()->currentAccount());

        return back();
    }
}
