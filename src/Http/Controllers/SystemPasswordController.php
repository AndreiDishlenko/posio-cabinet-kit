<?php

namespace Posio\CabinetKit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Posio\CabinetKit\Support\CabinetRedirects;
use Posio\CabinetKit\Support\SystemPasswordPolicy;

class SystemPasswordController extends Controller
{
    /**
     * Reached only through the gate. Opened directly by an account that has
     * nothing to change, it steps aside instead of showing a dead form.
     */
    public function screen(Request $request)
    {
        $user = $request->user();

        if (! SystemPasswordPolicy::mustChangePassword($user)) {
            return redirect(CabinetRedirects::url('home'));
        }

        return Inertia::render('pages/SystemPassword', [
            'email' => $user->email,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        // Same condition the gate uses: no seeded password left, nothing to force.
        abort_unless(SystemPasswordPolicy::mustChangePassword($user), 403);

        $validated = $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
                function (string $attribute, mixed $value, callable $fail) use ($user) {
                    if (SystemPasswordPolicy::isSeededPassword($user, (string) $value)) {
                        $fail(__('Choose a password other than the installed one.'));
                    }
                },
            ],
        ]);

        $user->forceFill(['password' => Hash::make($validated['password'])])->save();

        return redirect(CabinetRedirects::url('home'));
    }
}
