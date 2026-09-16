<?php

// Тексты сервисных писем кабинета. Хост переопределяет отдельные ключи файлом
// lang/vendor/cabinet-kit/{локаль}/mail.php — остальные остаются из пакета.
return [

    'greeting'           => 'Hello, :name!',
    'greeting_anonymous' => 'Hello!',
    'signoff'            => 'Regards,',
    'fallback_link'      => 'If the ":action" button does not work, copy this link into your browser:',
    'rights'             => 'All rights reserved.',

    'verify_email' => [
        'subject' => 'Confirm your email · :site',
        'heading' => 'Confirm your email',
        'intro'   => 'Thank you for signing up for :site. To finish, confirm that this address belongs to you.',
        'action'  => 'Confirm email',
        'ignore'  => 'If you did not sign up for :site, just ignore this email.',
    ],

    'reset_password' => [
        'subject' => 'Password reset · :site',
        'heading' => 'Time to set a new password',
        'intro'   => 'You received this email because a password reset was requested for your :site account. Click the button below to set a new password.',
        'action'  => 'Reset password',
        'expire'  => 'The link is valid for :minutes min.',
        'ignore'  => 'If you did not request a password reset, just ignore this email — your password will stay unchanged.',
    ],

    'registration_approval_request' => [
        'subject' => 'New registration awaits approval: :email · :site',
        'heading' => 'A new registration awaits approval',
        'intro'   => ':name (:email) has signed up for :site. They cannot sign in to the cabinet until you approve the registration.',
        'action'  => 'Approve registration',
        'note'    => 'To approve, sign in with an account that is allowed to manage users.',
    ],

    'registration_approved' => [
        'subject' => 'Your registration has been approved · :site',
        'heading' => 'Your registration has been approved',
        'intro'   => 'An administrator of :site has approved your registration. You can now sign in to the cabinet.',
        'action'  => 'Sign in to the cabinet',
    ],

];
