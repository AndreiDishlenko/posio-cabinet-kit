<?php

namespace Posio\CabinetKit\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Письмо пользователю: администратор допустил его в кабинет — иначе он не узнает, что уже можно входить.
class RegistrationApproved extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = AuthMail::siteName();

        return (new MailMessage)
            ->subject(__('cabinet-kit::mail.registration_approved.subject', ['site' => $site]))
            ->markdown(config('cabinet-kit.auth_mail.views.registration_approved', 'cabinet-kit::mail.registration-approved'), [
                'user' => $notifiable,
                'siteName' => $site,
                'siteUrl' => config('app.url'),
                'actionUrl' => route('login'),
                'actionText' => __('cabinet-kit::mail.registration_approved.action'),
            ]);
    }
}
