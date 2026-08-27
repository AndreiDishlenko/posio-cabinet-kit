<?php

namespace Posio\CabinetKit\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Posio\CabinetKit\Support\CabinetRedirects;

// Корень кабинета — только точка входа: куда именно попадёт пользователь,
// решает карта перенаправлений хоста.
class HomeController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect(CabinetRedirects::url('home'));
    }
}
