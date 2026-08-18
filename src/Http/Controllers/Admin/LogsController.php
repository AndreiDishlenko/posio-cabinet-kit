<?php

namespace Posio\CabinetKit\Http\Controllers\Admin;

use Illuminate\Routing\Controller;

class LogsController extends Controller
{
    public function index()
    {
        return redirect('/admin/log-viewer');
    }
}
