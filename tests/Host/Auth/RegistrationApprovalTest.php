<?php

namespace Posio\CabinetKit\Tests\Host\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Posio\CabinetKit\Notifications\RegistrationApprovalRequest;
use Posio\CabinetKit\Notifications\RegistrationApproved;
use Posio\CabinetKit\Services\RegistrationApprovalService;
use Posio\CabinetKit\Tests\Host\HostTestCase;

/**
 * Одобрение самостоятельной регистрации идёт поверх обязательного подтверждения
 * почты: письмо и ссылка подтверждения работают, но подтверждённый пользователь
 * не входит в кабинет до решения администратора.
 */
class RegistrationApprovalTest extends HostTestCase
{
    protected $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->approver = $this->makeSystemAdministrator();

        config([
            'cabinet-kit.registration' => 'approval',
            'cabinet_onboarding.registration_approval_emails' => $this->approver->email,
        ]);
    }

    protected function registerManually()
    {
        $email = 'approval-test-'.Str::lower(Str::random(12)).'@example.com';

        $this->post(route('register'), [
            'name' => 'Approval Test',
            'email' => $email,
            'password' => $this->password,
            'password_confirmation' => $this->password,
        ])->assertRedirect(route('verification.notice'));

        return $this->findUserByEmail($email);
    }

    protected function verifyEmail($user)
    {
        $url = URL::signedRoute('verification.verify', [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        return $this->actingAs($user)->get($url);
    }

    protected function approvals(): RegistrationApprovalService
    {
        return app(RegistrationApprovalService::class);
    }

    public function test_open_mode_does_not_hold_manual_registration_for_approval(): void
    {
        config(['cabinet-kit.registration' => 'open']);

        $user = $this->registerManually();

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->approval_requested_at);
        $this->assertFalse($this->approvals()->isPending($user->fresh()));
        Notification::assertNotSentTo($this->approver, RegistrationApprovalRequest::class);
    }

    public function test_manual_registration_requests_approval_but_still_allows_email_verification(): void
    {
        $user = $this->registerManually();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->approval_requested_at);
        $this->assertNull($user->fresh()->approved_at);

        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertSentTo(
            $this->approver,
            RegistrationApprovalRequest::class,
            fn (RegistrationApprovalRequest $notification) => $notification->registeredUser->is($user)
                && str_contains($notification->approveUrl,'/registration/approve/'.$user->getKey().'/')
        );

        $this->verifyEmail($user);

        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasVerifiedEmail());
        $this->assertTrue($this->approvals()->isPending($fresh));
    }

    public function test_verified_pending_user_is_blocked_from_login_cabinet_and_api(): void
    {
        $user = $this->registerManually();
        $this->verifyEmail($user);

        $this->actingAs($user->fresh())
            ->getJson($this->cabinetRoute('settings'))
            ->assertUnauthorized();
        $this->assertGuest();

        $this->actingAs($user->fresh())
            ->get($this->cabinetRoute('settings'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'registration-pending-approval');
        $this->assertGuest();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => $this->password,
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_approval_link_requires_user_management_permission(): void
    {
        $user = $this->registerManually();
        $ordinaryUser = $this->makeUser();

        $this->assertFalse($ordinaryUser->canSystem(RegistrationApprovalService::APPROVER_PERMISSION));

        $this->actingAs($ordinaryUser)
            ->get($this->approvals()->approvalUrl($user))
            ->assertForbidden();

        $this->assertNull($user->fresh()->approved_at);
    }

    public function test_authorized_approval_link_approves_user_and_restores_login(): void
    {
        $user = $this->registerManually();
        $this->verifyEmail($user);

        $this->actingAs($this->approver)
            ->get($this->approvals()->approvalUrl($user))
            ->assertOk()
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('pages/Auth/RegistrationApproval', false)
                ->where('outcome', 'registration-approved')
                ->where('user_email', $user->email));

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->approved_at);
        $this->assertSame((int) $this->approver->getKey(), (int) $fresh->approved_by);
        $this->assertFalse($this->approvals()->isPending($fresh));
        Notification::assertSentTo($fresh, RegistrationApproved::class);

        // Повторный переход по той же ссылке ничего не меняет и говорит об этом.
        $this->get($this->approvals()->approvalUrl($user))
            ->assertOk()
            ->assertInertia(fn (AssertInertia $page) => $page->where('outcome', 'registration-already-approved'));

        $this->post(route('logout'));
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => $this->password,
        ])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($fresh);
    }

    public function test_admin_api_can_approve_pending_registration(): void
    {
        $user = $this->registerManually();

        $response = $this->actingAs($this->approver)
            ->postJson($this->cabinetRoute('users.approve'), ['id' => $user->getKey()])
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertNotNull($response->json('approved_at'));
        $this->assertSame((int) $this->approver->getKey(), (int) $user->fresh()->approved_by);
        Notification::assertSentTo($user, RegistrationApproved::class);

        $this->actingAs($this->approver)
            ->postJson($this->cabinetRoute('users.approve'), ['id' => $user->getKey()])
            ->assertStatus(422);
    }
}
