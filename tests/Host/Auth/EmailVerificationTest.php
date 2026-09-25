<?php

namespace Posio\CabinetKit\Tests\Host\Auth;

use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Posio\CabinetKit\Support\CabinetRedirects;
use Posio\CabinetKit\Tests\Host\HostTestCase;

/**
 * Переходы по ссылке подтверждения почты: на том же устройстве, без входа, под
 * другим аккаунтом, повторно и с повреждённой подписью. Каждый из этих случаев
 * когда-то заканчивался 403, 405 или бесконечным перенаправлением.
 */
class EmailVerificationTest extends HostTestCase
{
    protected function verificationUrl($user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);
    }

    // Системная роль выдаётся только подтвердившим почту, и именно в системном контексте ролей.
    public function test_default_system_role_is_assigned_after_verification(): void
    {
        $role = config('cabinet-kit.default_system_role');
        $user = $this->makeUser(verified: false);

        $this->assertFalse($user->hasSystemRole($role), 'Unverified user must not have a system role yet.');

        Event::fake([Verified::class]);

        $this->actingAs($user)->get($this->verificationUrl($user));

        Event::assertDispatched(Verified::class);

        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasVerifiedEmail());
        $this->assertTrue($fresh->hasSystemRole($role), 'Verified user must receive the default system role.');
        $this->assertTrue(
            DB::table(config('permission.table_names.model_has_roles'))
                ->where(config('permission.column_names.model_morph_key'), $user->getKey())
                ->where(config('permission.column_names.team_foreign_key', 'team_id'), (int) config('cabinet-kit.system_team_id', 0))
                ->exists(),
            'System role assignment must be scoped to the system team.'
        );
    }

    public function test_link_opened_by_signed_in_owner_leads_to_cabinet(): void
    {
        $user = $this->makeUser(verified: false);

        $this->actingAs($user)
            ->get($this->verificationUrl($user))
            ->assertRedirect(CabinetRedirects::url('after_verify'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verifying_email_while_logged_out_requires_sign_in_first(): void
    {
        $user = $this->makeUser(verified: false);
        $url = $this->verificationUrl($user);

        // Гость переходит по ссылке — почта не подтверждается до входа в эту учётную запись.
        $this->get($url)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'verification-sign-in-required')
            ->assertSessionHas('email', $user->email)
            ->assertSessionHas('url.intended', $url);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->get(route('login'))->assertOk();

        // После входа та же ссылка подтверждает почту.
        $this->actingAs($user)->get($url);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_link_opened_while_signed_in_as_other_user_does_not_confirm_email(): void
    {
        Event::fake([Verified::class]);

        $target = $this->makeUser(verified: false);
        $other = $this->makeUser();
        $url = $this->verificationUrl($target);

        $this->actingAs($other)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('pages/Auth/EmailVerificationOutcome', false)
                ->where('outcome', 'email-verification-sign-in-required')
                ->where('verified_email', $target->email)
                ->where('current_email', $other->email));

        // Чужой вход почту не подтверждает.
        $this->assertFalse($target->fresh()->hasVerifiedEmail());
        Event::assertNotDispatched(Verified::class);

        // Сессия вошедшего не трогается: он сам решает, остаться или войти в подтверждаемую учётную запись.
        $this->assertAuthenticatedAs($other);

        // Переход ко входу в нужную учётную запись возвращает после входа на ту же ссылку.
        $this->post(route('verification.switch-account'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('email', $target->email)
            ->assertSessionHas('url.intended', $url);

        $this->assertGuest();
    }

    public function test_repeated_link_while_signed_in_as_other_user_says_email_was_confirmed_earlier(): void
    {
        $target = $this->makeUser();
        $other = $this->makeUser();

        $this->actingAs($other)
            ->get($this->verificationUrl($target))
            ->assertOk()
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('pages/Auth/EmailVerificationOutcome', false)
                ->where('outcome', 'email-already-verified'));
    }

    public function test_repeated_link_while_logged_out_says_email_was_confirmed_earlier(): void
    {
        $user = $this->makeUser();

        $this->get($this->verificationUrl($user))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'email-already-verified');
    }

    // Почту сменили после отправки письма: подпись верна, но ссылка уже ничья.
    public function test_link_for_changed_email_leads_to_sign_in_with_explanation(): void
    {
        $user = $this->makeUser(verified: false);
        $url = $this->verificationUrl($user);

        $user->forceFill(['email' => 'changed-'.$user->email])->save();

        $this->get($url)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'verification-link-invalid');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    // HEAD — предварительная проверка ссылки почтовыми сканерами, им отвечают так же, как браузеру.
    public function test_damaged_signature_leads_to_sign_in_with_explanation_for_browser_and_mail_scanner(): void
    {
        $target = $this->makeUser(verified: false);
        $damaged_url = preg_replace('/signature=[0-9a-f]+/', 'signature=damaged', $this->verificationUrl($target));

        $this->get($damaged_url)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'verification-link-broken');

        $this->call('HEAD', $damaged_url)->assertRedirect(route('login'));

        $this->assertFalse($target->fresh()->hasVerifiedEmail());
    }

    public function test_verification_email_can_be_resent(): void
    {
        $user = $this->makeUser(verified: false);

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_notice_screen_sends_verified_user_to_cabinet_without_redirect_loop(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('verification.notice'))
            ->assertRedirect(CabinetRedirects::url('after_verify'));
    }
}
