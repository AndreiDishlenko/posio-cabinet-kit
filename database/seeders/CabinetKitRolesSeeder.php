<?php

namespace Posio\CabinetKit\Database\Seeders;

use Illuminate\Database\Seeder;
use Posio\CabinetKit\Support\CabinetKitRoles;

/**
 * Роли и права заводятся сами после каждого наката миграций. Класс оставлен
 * для хостов, чьи сидеры уже вызывают его по имени.
 */
class CabinetKitRolesSeeder extends Seeder
{
    public function run(): void
    {
        CabinetKitRoles::sync();
    }
}
