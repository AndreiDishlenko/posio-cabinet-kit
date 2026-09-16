<?php

namespace Posio\CabinetKit\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Письмо администратору: новый пользователь ждёт допуска в кабинет. Тексты и шаблон —
// из пакета и переопределяются хостом так же, как письма подтверждения почты.
class RegistrationApprovalRequest extends Notification
{
    use Queueable;

    public function __construct(
        public $registeredUser,
        public string $approveUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = AuthMail::siteName();

        return (new MailMessage)
            ->subject(__('cabinet-kit::mail.registration_approval_request.subject', ['site' => $site, 'email' => $this->registeredUser->email]))
            ->markdown(config('cabinet-kit.auth_mail.views.registration_approval_request', 'cabinet-kit::mail.registration-approval-request'), [
                'user' => $notifiable,
                'registeredUser' => $this->registeredUser,
                'siteName' => $site,
                'siteUrl' => config('app.url'),
                'actionUrl' => $this->approveUrl,
                'actionText' => __('cabinet-kit::mail.registration_approval_request.action'),
            ]);
    }
}
