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

    /** Реквизиты организации — правит владелец или управляющий составом. */
    public function update(Request $request)
    {
        $this->authorize('manage-members');

        $validated = $request->validate([
            'name' => 'required|string|min:2|max:255',
            'description' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:255',
            'url' => 'nullable|string|max:255',
        ]);

        $account = $request->user()->currentAccount();

        abort_unless($account, 404, 'No active account.');

        $account->fillProfile($validated);
        $account->name = $validated['name'];
        $account->save();

        return back();
    }

    public function addLogo(Request $request)
    {
        $this->authorize('manage-members');

        $request->validate([
            'photo' => ['required', 'image', 'max:8192', 'dimensions:min_width=100,min_height=100,max_width=1000,max_height=1000'],
        ]);

        $account = $request->user()->currentAccount();

        abort_unless($account, 404, 'No active account.');

        $account->fillProfile(['logo' => $request->file('photo')->store('logos', 'public')]);
        $account->save();

        return back();
    }

    public function inviteMember(Request $request)
    {
        $this->authorize('manage-account');

        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:'.config('cabinet-kit.users_table', 'users').',id',
            'email' => 'nullable|email|exists:'.config('cabinet-kit.users_table', 'users').',email',
            'role' => 'nullable|string',
        ]);

        abort_unless(! empty($validated['user_id']) || ! empty($validated['email']), 422, 'Select a user or enter an existing user email.');

        $userModel = config('cabinet-kit.user_model');
        $member = ! empty($validated['user_id'])
            ? $userModel::findOrFail($validated['user_id'])
            : $userModel::query()->where('email', $validated['email'])->firstOrFail();

        abort_if($member->getKey() === $request->user()->getKey(), 422, 'You are already a member of this account.');

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

        abort_if($member->getKey() === $request->user()->getKey(), 422, 'You cannot change your own account role.');

        $this->accountService->setMemberRole($member, $request->user()->currentAccount(), $validated['role']);

        return back();
    }

    public function removeMember(Request $request)
    {
        $this->authorize('manage-account');

        $validated = $request->validate(['user_id' => 'required|integer']);

        $userModel = config('cabinet-kit.user_model');
        $member = $userModel::findOrFail($validated['user_id']);

        abort_if($member->getKey() === $request->user()->getKey(), 422, 'You cannot remove yourself from the active account.');

        $this->accountService->removeMember($member, $request->user()->currentAccount());

        return back();
    }
}
