<?php

namespace Posio\CabinetKit\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Inertia\Inertia;

class LogsController extends Controller
{
    public function index()
    {
        return Inertia::render('pages/Logs');
    }
}
