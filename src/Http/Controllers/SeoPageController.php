<?php

namespace Posio\CabinetKit\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;

// Редактор постраничной SEO-меты. Список записей страница берёт через API,
// поэтому пропсов у неё нет.
class SeoPageController extends Controller
{
    public function index()
    {
        return Inertia::render('pages/Seo', []);
    }
}
