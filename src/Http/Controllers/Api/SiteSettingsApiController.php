<?php

namespace Posio\CabinetKit\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Posio\CabinetKit\Services\SiteSettingsService;

// API операторского раздела «Налаштування сайту» (право sysper-site, см.
// routes/cabinet.php).
class SiteSettingsApiController extends Controller
{
    public function __construct(protected SiteSettingsService $settings) {}

    public function index() {
        return response()->json($this->payload());
    }

    public function update(Request $request) {
        $validated = $request->validate([
            'site_name' => 'nullable|string|max:60',
        ]);

        $this->settings->setValue('site_name', $validated['site_name'] ?? null);

        return response()->json($this->payload());
    }

    public function updateTheme(Request $request) {
        $validated = $request->validate([
            'key'   => ['required', Rule::in(array_keys(SiteSettingsService::THEMES))],
            'value' => ['required', Rule::in(SiteSettingsService::THEME_VALUES)],
        ]);

        $this->settings->setTheme($validated['key'], $validated['value']);

        return response()->json($this->payload());
    }

    public function uploadImage(Request $request) {
        $validated = $request->validate([
            'key'  => ['required', Rule::in(array_keys(SiteSettingsService::IMAGES))],
            // Правило image отбрасывает векторные файлы и .ico, а именно они и нужны
            // для значка вкладки и логотипов, поэтому список расширений задан явно.
            'file' => 'required|file|mimes:png,jpg,jpeg,webp,svg,ico|max:2048',
        ]);

        $this->settings->setImage($validated['key'], $validated['file']);

        return response()->json($this->payload());
    }

    public function deleteImage(Request $request) {
        $validated = $request->validate([
            'key' => ['required', Rule::in(array_keys(SiteSettingsService::IMAGES))],
        ]);

        $this->settings->clearImage($validated['key']);

        return response()->json($this->payload());
    }

    private function payload() : array {
        $images = [];

        // Отдаём действующую картинку вместе с признаком «штатный файл проекта»:
        // по нему интерфейс решает, предлагать ли удаление.
        foreach ( array_keys(SiteSettingsService::IMAGES) as $key )
            $images[$key] = [
                'url'        => $this->settings->imageUrl($key),
                'is_default' => !$this->settings->hasCustomImage($key),
            ];

        $themes = [];

        foreach ( array_keys(SiteSettingsService::THEMES) as $key )
            $themes[$key] = $this->settings->theme($key);

        return [
            'site_name'    => $this->settings->get('site_name', ''),
            // Что подставится, если поле оставить пустым.
            'name_default' => config('seo.brand_name', config('app.name', 'Cabinet')),
            'images'       => $images,
            'themes'       => $themes,
        ];
    }
}
