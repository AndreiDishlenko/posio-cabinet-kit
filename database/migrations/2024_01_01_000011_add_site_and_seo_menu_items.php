<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Разделы бренда и SEO появились позже первой установки, а сидер меню
// запускается только при установке — поэтому пункты добавляет миграция.
// Держать в синхроне с database/seeders/CabinetKitAdminLinksSeeder.php.
return new class extends Migration
{
    protected array $items = [
        [
            'order_id' => 6,
            'name' => 'Site settings',
            'icon' => 'mdi:web',
            'route' => 'cabinet-kit.sitesettings',
        ],
        [
            'order_id' => 7,
            'name' => 'Cabinet settings',
            'icon' => 'mdi:monitor-dashboard',
            'route' => 'cabinet-kit.cabinetsettings',
        ],
        [
            'order_id' => 8,
            'name' => 'SEO',
            'icon' => 'mdi:google',
            'route' => 'cabinet-kit.seo',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('admin_links')) {
            return;
        }

        foreach ($this->items as $item) {
            if (DB::table('admin_links')->where('route', $item['route'])->exists()) {
                continue;
            }

            DB::table('admin_links')->insert([
                ...$item,
                'link' => null,
                'permissions' => 'sysper-site',
                'is_header' => false,
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_links')) {
            return;
        }

        DB::table('admin_links')
            ->whereIn('route', array_column($this->items, 'route'))
            ->delete();
    }
};
