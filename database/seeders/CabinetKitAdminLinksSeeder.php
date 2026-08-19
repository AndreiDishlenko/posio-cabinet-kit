<?php

namespace Posio\CabinetKit\Database\Seeders;

use Illuminate\Database\Seeder;
use Posio\CabinetKit\Models\AdminLink;

class CabinetKitAdminLinksSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'order_id' => 0,
                'name' => 'Administration',
                'icon' => null,
                'link' => null,
                'route' => null,
                'permissions' => null,
                'is_header' => true,
                'is_published' => true,
            ],
            [
                'order_id' => 1,
                'name' => 'Користувачі',
                'icon' => 'ph:users',
                'link' => null,
                'route' => 'cabinet-kit.users',
                'permissions' => 'sysper-users',
                'is_header' => false,
                'is_published' => true,
            ],
            [
                'order_id' => 2,
                'name' => 'Дозволи',
                'icon' => 'fluent-mdl2:permissions',
                'link' => null,
                'route' => 'cabinet-kit.permissions',
                'permissions' => 'sysper-roles',
                'is_header' => false,
                'is_published' => true,
            ],
            [
                'order_id' => 3,
                'name' => 'Ролі акаунту',
                'icon' => 'fluent-mdl2:permissions',
                'link' => null,
                'route' => 'cabinet-kit.permissions.account',
                'permissions' => 'sysper-roles',
                'is_header' => false,
                'is_published' => true,
            ],
            [
                'order_id' => 4,
                'name' => 'Logs',
                'icon' => 'ix:log',
                'link' => '/admin/log-viewer',
                'route' => null,
                'permissions' => 'sysper-log-view',
                'is_header' => false,
                'is_published' => true,
            ],
            [
                'order_id' => 5,
                'name' => 'Settings',
                'icon' => 'proicons:settings',
                'link' => null,
                'route' => 'cabinet-kit.settings',
                'permissions' => null,
                'is_header' => false,
                'is_published' => true,
            ],
        ];

        // Logs used to be a placeholder page of the cabinet itself; the row is
        // repointed here so the item below matches it instead of doubling it.
        AdminLink::query()
            ->where('name', 'Logs')
            ->where('route', 'cabinet-kit.logs')
            ->update([
                'link' => '/admin/log-viewer',
                'route' => null,
            ]);

        foreach ($items as $item) {
            AdminLink::query()->updateOrCreate(
                ['route' => $item['route'], 'name' => $item['name']],
                $item,
            );
        }
    }
}
