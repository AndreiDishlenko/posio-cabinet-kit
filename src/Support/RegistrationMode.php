<?php

namespace Posio\CabinetKit\Support;

// Режим самостоятельной регистрации в кабинете. Непонятное значение в настройке
// читается как «закрыта» — опечатка не должна открывать кабинет всем.
class RegistrationMode
{
    public const CLOSED = 'closed';

    public const APPROVAL = 'approval';

    public const OPEN = 'open';

    public static function current(): string
    {
        $mode = strtolower(trim((string) config('cabinet-kit.registration', self::CLOSED)));

        return in_array($mode, [self::APPROVAL, self::OPEN], true) ? $mode : self::CLOSED;
    }

    // Новый человек может завести себе учётку сам — сразу или с ожиданием одобрения.
    public static function allowsSignUp(): bool
    {
        return self::current() !== self::CLOSED;
    }

    public static function requiresApproval(): bool
    {
        return self::current() === self::APPROVAL;
    }
}
