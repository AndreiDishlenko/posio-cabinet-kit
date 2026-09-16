<?php

namespace Posio\CabinetKit\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Posio\CabinetKit\Services\SiteSettingsService;

// Письма подтверждения почты и сброса пароля собираются из шаблонов и переводов
// пакета вместо стандартных английских писем фреймворка. Уведомления остаются
// штатными, поэтому хост без правки модели пользователя получает их сразу.
class AuthMail
{
    public static function register(): void
    {
        if (! config('cabinet-kit.auth_mail.enabled', true)) {
            return;
        }

        // Уже заданная кем-то сборка письма остаётся за ним.
        if (! VerifyEmail::$toMailCallback) {
            VerifyEmail::toMailUsing([static::class, 'verifyEmail']);
        }

        if (! ResetPassword::$toMailCallback) {
            ResetPassword::toMailUsing([static::class, 'resetPassword']);
        }
    }

    public static function verifyEmail($notifiable, string $url): MailMessage
    {
        $site = static::siteName();

        return (new MailMessage)
            ->subject(__('cabinet-kit::mail.verify_email.subject', ['site' => $site]))
            ->markdown(config('cabinet-kit.auth_mail.views.verify_email', 'cabinet-kit::mail.verify-email'), [
                'user' => $notifiable,
                'siteName' => $site,
                'siteUrl' => config('app.url'),
                'actionUrl' => $url,
                'actionText' => __('cabinet-kit::mail.verify_email.action'),
            ]);
    }

    public static function resetPassword($notifiable, string $token): MailMessage
    {
        $site = static::siteName();
        $broker = config('auth.defaults.passwords');

        return (new MailMessage)
            ->subject(__('cabinet-kit::mail.reset_password.subject', ['site' => $site]))
            ->markdown(config('cabinet-kit.auth_mail.views.reset_password', 'cabinet-kit::mail.reset-password'), [
                'user' => $notifiable,
                'siteName' => $site,
                'siteUrl' => config('app.url'),
                'actionUrl' => static::resetUrl($notifiable, $token),
                'actionText' => __('cabinet-kit::mail.reset_password.action'),
                'expireMinutes' => config("auth.passwords.{$broker}.expire", 60),
            ]);
    }

    // Своя сборка ссылки хоста уважается так же, как в стандартном письме.
    protected static function resetUrl($notifiable, string $token): string
    {
        if (ResetPassword::$createUrlCallback) {
            return call_user_func(ResetPassword::$createUrlCallback, $notifiable, $token);
        }

        return url(route('password.reset', [
            'token' => $token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }

    // Письмо не должно теряться из-за недоступной базы настроек сайта.
    protected static function siteName(): string
    {
        try {
            return app(SiteSettingsService::class)->siteName();
        } catch (\Throwable) {
            return (string) config('app.name');
        }
    }
}
