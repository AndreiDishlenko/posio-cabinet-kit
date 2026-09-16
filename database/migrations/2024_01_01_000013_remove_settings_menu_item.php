<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// В меню posio.cabinet пункта настроек нет: профиль открывается из блока
// пользователя в боковом меню, а в разделе администрирования пункт дублировал его.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_links')) {
            return;
        }

        DB::table('admin_links')
            ->where('route', 'cabinet-kit.settings')
            ->where('is_header', false)
            ->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_links') || DB::table('admin_links')->where('route', 'cabinet-kit.settings')->exists()) {
            return;
        }

        DB::table('admin_links')->insert([
            'order_id' => 5,
            'name' => 'Settings',
            'icon' => 'proicons:settings',
            'link' => null,
            'route' => 'cabinet-kit.settings',
            'permissions' => null,
            'is_header' => false,
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
