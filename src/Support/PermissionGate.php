<?php

namespace Posio\CabinetKit\Support;

// Проверка прав, которую маршрут страницы кабинета ставит посредником. Вынесена в статический
// метод, чтобы до перехода узнать, откроется ли страница: по ней решается, куда вести после
// входа, подтверждения почты и отказа в доступе.
interface PermissionGate
{
    public static function allows($user, string $parameters): bool;
}
