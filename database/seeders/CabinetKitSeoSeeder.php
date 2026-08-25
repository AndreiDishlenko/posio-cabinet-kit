<?php

namespace Posio\CabinetKit\Database\Seeders;

use Illuminate\Database\Seeder;
use Posio\CabinetKit\Models\SeoMeta;

/**
 * Заготовка SEO-записи для главной страницы.
 *
 * Раздел SEO без единой записи выглядит сломанным, а главная есть у любого
 * сайта — поэтому одна строка заводится сразу, с названием бренда в заголовке.
 * Уже существующая запись не трогается: сидер безопасно запускать повторно.
 */
class CabinetKitSeoSeeder extends Seeder
{
    public function run(): void
    {
        $route = (string) config('cabinet-kit.seo.home_route', 'home');

        if (SeoMeta::withTrashed()->where('route_name', $route)->exists()) {
            return;
        }

        $brand = (string) config('seo.brand_name', config('app.name', 'Cabinet'));

        SeoMeta::create([
            'route_name' => $route,
            // Пусто — «для всех локалей»: сайт на одном языке не должен получать
            // запись, привязанную к локали, которой у него нет.
            'locale' => null,
            'page_name' => 'Home',
            'meta_title' => $brand,
            'meta_description' => '',
            'index' => 1,
            'is_published' => true,
            'changeFrequency' => 'weekly',
            'priority' => 1.0,
        ]);
    }
}
