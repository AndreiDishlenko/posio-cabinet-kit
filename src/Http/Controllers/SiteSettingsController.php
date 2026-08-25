<?php

namespace Posio\CabinetKit\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;

// Две одинаковые по устройству страницы бренда: публичная часть и кабинет
// оформляются раздельно. Данные страницы подтягивают сами через API настроек,
// поэтому пропсов здесь нет.
class SiteSettingsController extends Controller
{
    public function site()
    {
        return Inertia::render('pages/SiteBrandSettings', []);
    }

    public function cabinet()
    {
        return Inertia::render('pages/CabinetBrandSettings', []);
    }
}
