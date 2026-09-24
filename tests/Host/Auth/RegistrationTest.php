<?php

namespace Posio\CabinetKit\Tests\Host\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Posio\CabinetKit\Tests\Host\HostTestCase;

class RegistrationTest extends HostTestCase
{
    protected function registrationForm(array $overrides = []): array
    {
        return [
            'name' => 'Registration Test',
            'email' => 'registration-test@example.com',
            'password' => $this->password,
            'password_confirmation' => $this->password,
            ...$overrides,
        ];
    }

    public function test_registration_screen_can_be_rendered_when_registration_is_open(): void
    {
        config(['cabinet-kit.registration' => 'open']);

        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (AssertInertia $page) => $page->component('pages/Auth/Register', false));
    }

    // Закрытая регистрация прячет и форму, и её приём, а не только ссылку на неё.
    public function test_closed_registration_answers_not_found(): void
    {
        $this->get(route('register'))->assertNotFound();
        $this->post(route('register'), $this->registrationForm())->assertNotFound();

        $this->assertFalse($this->userModel()::query()->where('email', 'registration-test@example.com')->exists());
        $this->assertGuest();
    }

    public function test_manual_email_and_password_registration_sends_verification_email(): void
    {
        config(['cabinet-kit.registration' => 'open']);

        $this->post(route('register'), $this->registrationForm())
            ->assertRedirect(route('verification.notice'));

        $user = $this->findUserByEmail('registration-test@example.com');

        $this->assertAuthenticatedAs($user);
        $this->assertFalse(
            $user->hasVerifiedEmail(),
            'Email entered manually must remain unverified until the user opens the verification link.'
        );
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get(route('verification.notice'))
            ->assertOk()
            ->assertInertia(fn (AssertInertia $page) => $page->component('pages/Auth/VerifyEmail', false));
    }

    public function test_unverified_user_is_held_on_verification_notice(): void
    {
        config(['cabinet-kit.registration' => 'open']);

        $this->post(route('register'), $this->registrationForm());

        $this->get($this->cabinetRoute('settings'))->assertRedirect(route('verification.notice', absolute: false));
    }

    public function test_registration_rejects_taken_email_and_unconfirmed_password(): void
    {
        config(['cabinet-kit.registration' => 'open']);

        $existing = $this->makeUser();

        $this->post(route('register'), $this->registrationForm(['email' => $existing->email]))
            ->assertSessionHasErrors('email');

        $this->post(route('register'), $this->registrationForm(['password_confirmation' => 'something-else']))
            ->assertSessionHasErrors('password');

        $this->assertGuest();
    }
}
