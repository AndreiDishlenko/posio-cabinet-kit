<?php

namespace Posio\CabinetKit\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Группа меню модуля в таблице меню кабинета — для миграций модуля:
 *
 *     ModuleMenu::install('Catalog', [
 *         ['name' => 'Products', 'icon' => 'mdi:package-variant', 'route' => 'catalog.products'],
 *     ], 'sysper-catalog');
 *
 *     ModuleMenu::uninstall('Catalog', ['catalog.products']);
 *
 * Группа встаёт в конец меню, после всего, что уже есть: номера порядка хоста
 * и пакета не сдвигаются. Повторный запуск ничего не дублирует — пункт узнаётся
 * по маршруту или ссылке, заголовок по имени.
 */
class ModuleMenu
{
    protected const TABLE = 'admin_links';

    /**
     * @param  array<int, array{name: string, icon?: string, route?: string, link?: string, permissions?: string}>  $items
     */
    public static function install(string $header, array $items, ?string $permission = null): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $order = (int) DB::table(self::TABLE)->max('order_id');

        if (! DB::table(self::TABLE)->where('is_header', true)->where('name', $header)->exists()) {
            static::insert([
                'order_id' => ++$order,
                'name' => $header,
                'permissions' => $permission,
                'is_header' => true,
            ]);
        }

        foreach ($items as $item) {
            if (static::exists($item)) {
                continue;
            }

            static::insert([
                'order_id' => ++$order,
                'name' => $item['name'],
                'icon' => $item['icon'] ?? null,
                'route' => $item['route'] ?? null,
                'link' => $item['link'] ?? null,
                'permissions' => $item['permissions'] ?? $permission,
                'is_header' => false,
            ]);
        }
    }

    /**
     * @param  string[]  $targets  маршруты или ссылки пунктов модуля
     */
    public static function uninstall(string $header, array $targets): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        DB::table(self::TABLE)
            ->where('is_header', false)
            ->where(fn ($query) => $query->whereIn('route', $targets)->orWhereIn('link', $targets))
            ->delete();

        DB::table(self::TABLE)->where('is_header', true)->where('name', $header)->delete();
    }

    protected static function exists(array $item): bool
    {
        $column = filled($item['route'] ?? null) ? 'route' : 'link';
        $value = $item[$column] ?? null;

        return $value !== null && DB::table(self::TABLE)->where($column, $value)->exists();
    }

    protected static function insert(array $row): void
    {
        DB::table(self::TABLE)->insert([
            'icon' => null,
            'route' => null,
            'link' => null,
            ...$row,
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
