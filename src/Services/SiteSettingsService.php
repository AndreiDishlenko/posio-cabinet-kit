<?php

namespace Posio\CabinetKit\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Posio\CabinetKit\Models\SiteSetting;

/*
|--------------------------------------------------------------------------
| Настройки главного сайта
|--------------------------------------------------------------------------
|
| Бренд и графическая идентичность, которые оператор меняет из кабинета вместо
| правки конфигов и статики. Значение перекрывает дефолт проекта, но уступает
| странично заданной SEO-мете: имя сайта — только запасное значение.
|
| Читается на каждом рендере страницы, поэтому набор держится в кэше и
| сбрасывается любой записью.
|
*/
class SiteSettingsService {

    const CACHE_KEY = 'site_settings';

    // Публичная часть и кабинет оформлены по-разному, поэтому значок вкладки и
    // логотипы у них раздельные; у логотипов свои начертания под тёмную и светлую
    // тему. `default` — обезличенная заготовка, одинаковая для любой установки:
    // фирменные файлы конкретного проекта загружаются поверх неё как обычная
    // настройка, а не зашиваются в код.
    const IMAGES = [
        'main_favicon' => [
            'folder'  => 'site',
            'default' => '/brand-assets/favicon.png',
        ],
        'main_logo_dark' => [
            'folder'  => 'site',
            'default' => '/brand-assets/logo_dark_theme.svg',
        ],
        'main_logo_light' => [
            'folder'  => 'site',
            'default' => '/brand-assets/logo_light_theme.svg',
        ],
        'cabinet_favicon' => [
            'folder'  => 'site',
            'default' => '/brand-assets/favicon.png',
        ],
        'cabinet_logo_dark' => [
            'folder'  => 'site',
            'default' => '/brand-assets/logo_dark_theme.svg',
        ],
        'cabinet_logo_light' => [
            'folder'  => 'site',
            'default' => '/brand-assets/logo_light_theme.svg',
        ],
        // Знак — короткое начертание для свёрнутой боковой панели кабинета.
        'cabinet_symbol_dark' => [
            'folder'  => 'site',
            'default' => '/brand-assets/symbol_dark_theme.svg',
        ],
        'cabinet_symbol_light' => [
            'folder'  => 'site',
            'default' => '/brand-assets/symbol_light_theme.svg',
        ],
    ];

    // Тема оформления, с которой часть открывается, пока посетитель не выбрал свою.
    const THEMES = [
        'main_theme'    => 'dark',
        'cabinet_theme' => 'dark',
    ];

    const THEME_VALUES = ['dark', 'light'];

    // Фирменные файлы этой установки, которые до появления раздела были зашиты в
    // разметку. Переносятся в настройки один раз, как будто их загрузил оператор.
    //
    // Лежат в публичной папке, а не в хранилище настроек: хранилище исключено из
    // репозитория, поэтому на развёрнутой копии перенести бренд можно только из
    // файлов, приехавших вместе с кодом. Эта же папка — запас для ручной загрузки
    // через раздел настроек, поэтому имена совпадают с ключами настроек.
    const LEGACY_BRAND = [
        'main_favicon'         => 'temp/main_favicon.ico',
        'main_logo_dark'       => 'temp/main_logo_dark.svg',
        'main_logo_light'      => 'temp/main_logo_light.svg',
        'cabinet_favicon'      => 'temp/cabinet_favicon.ico',
        'cabinet_logo_dark'    => 'temp/cabinet_logo_dark.svg',
        'cabinet_logo_light'   => 'temp/cabinet_logo_light.svg',
        'cabinet_symbol_dark'  => 'temp/cabinet_symbol_dark.svg',
        'cabinet_symbol_light' => 'temp/cabinet_symbol_light.svg',
    ];

    public function all() : array {
        return Cache::rememberForever(self::CACHE_KEY, fn() => SiteSetting::pluck('value', 'key')->all());
    }

    public function get(string $key, $default = null) {
        $value = $this->all()[$key] ?? null;

        return ( $value === null || $value === '' ) ? $default : $value;
    }

    public function setValue(string $key, ?string $value) : void {
        SiteSetting::updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_KEY);
    }

    public function setImage(string $key, UploadedFile $file) : void {
        $this->deleteStoredFile($key);

        $this->setValue($key, $file->store(self::IMAGES[$key]['folder'], 'public'));
    }

    public function clearImage(string $key) : void {
        $this->deleteStoredFile($key);

        $this->setValue($key, null);
    }

    // Действующий URL картинки: загруженная оператором либо штатный файл проекта.
    // Никогда не пусто — потребителям не нужен собственный запасной путь.
    public function imageUrl(string $key) : ?string {
        $path = $this->get($key);

        return $path ? Storage::disk('public')->url($path) : ( self::IMAGES[$key]['default'] ?? null );
    }

    // Загружена ли своя картинка — по этому признаку интерфейс отличает штатный
    // файл от заменённого, а корневой шаблон сайта решает, добавлять ли парные
    // значки вкладки разных размеров.
    public function hasCustomImage(string $key) : bool {
        return (bool) $this->get($key);
    }

    // Тема, с которой часть открывается у посетителя без собственного выбора.
    public function theme(string $key) : string {
        $value = $this->get($key);

        return in_array($value, self::THEME_VALUES, true) ? $value : self::THEMES[$key];
    }

    public function setTheme(string $key, string $value) : void {
        $this->setValue($key, in_array($value, self::THEME_VALUES, true) ? $value : self::THEMES[$key]);
    }

    // Перенос фирменных файлов установки в настройки: копия ложится в хранилище,
    // и дальше картинка живёт как загруженная. Уже настроенные ключи не трогаются,
    // поэтому повторный запуск ничего не перезаписывает.
    public function importLegacyBrand() : array {
        $imported = [];

        foreach ( self::LEGACY_BRAND as $key => $relative_path ) {
            if ( $this->hasCustomImage($key) )
                continue;

            $source = public_path($relative_path);

            if ( !is_file($source) )
                continue;

            $target = self::IMAGES[$key]['folder'] . '/' . $key . '-' . substr(md5_file($source), 0, 8) . '.' . pathinfo($source, PATHINFO_EXTENSION);

            Storage::disk('public')->put($target, file_get_contents($source));

            $this->setValue($key, $target);

            $imported[$key] = $target;
        }

        return $imported;
    }

    // Запасное имя бренда для заголовков вкладок и микроразметки.
    public function siteName() : string {
        return $this->get('site_name', config('seo.brand_name', config('app.name', 'Cabinet')));
    }

    // Идентичность для Vue-слоя: шапка публичной части и боковое меню кабинета
    // берут начертания отсюда, а не из зашитых в разметку путей.
    public function frontPayload() : array {
        return [
            'name' => $this->siteName(),
            'main' => [
                'logo_dark'  => $this->imageUrl('main_logo_dark'),
                'logo_light' => $this->imageUrl('main_logo_light'),
            ],
            'cabinet' => [
                'logo_dark'    => $this->imageUrl('cabinet_logo_dark'),
                'logo_light'   => $this->imageUrl('cabinet_logo_light'),
                'symbol_dark'  => $this->imageUrl('cabinet_symbol_dark'),
                'symbol_light' => $this->imageUrl('cabinet_symbol_light'),
            ],
        ];
    }

    private function deleteStoredFile(string $key) : void {
        $path = $this->get($key);

        if ( $path )
            Storage::disk('public')->delete($path);
    }
}
