<?php

namespace Posio\CabinetKit\Tests\Host\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Posio\CabinetKit\Support\CabinetRedirects;
use Posio\CabinetKit\Tests\Host\HostTestCase;

class AuthenticationTest extends HostTestCase
{
    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (AssertInertia $page) => $page->component('pages/Auth/Login', false));
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = $this->makeUser();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => $this->password,
        ])->assertRedirect(CabinetRedirects::url('after_login'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = $this->makeUser();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $this->actingAs($this->makeUser())
            ->post(route('logout'))
            ->assertRedirect(CabinetRedirects::url('after_logout'));

        $this->assertGuest();
    }

    public function test_guest_is_sent_from_cabinet_to_login(): void
    {
        $this->get($this->cabinetRoute('settings'))->assertRedirect(route('login'));
    }

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertInertia(fn (AssertInertia $page) => $page->component('pages/Auth/ForgotPassword', false));
    }

    public function test_password_reset_link_can_be_requested(): void
    {
        $user = $this->makeUser();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = $this->makeUser();
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('pages/Auth/ResetPassword', false)
                ->where('token', $token));

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'N3w-Passw0rd',
            'password_confirmation' => 'N3w-Passw0rd',
        ])->assertRedirect(route('login'));

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'N3w-Passw0rd',
        ]);

        $this->assertAuthenticatedAs($user);
    }
}
