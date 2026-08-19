<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_links')) {
            return;
        }

        Schema::create('admin_links', function (Blueprint $table) {
            $table->id();
            $table->integer('order_id')->default(0);
            $table->string('name', 80);
            $table->string('icon', 80)->nullable();
            $table->string('link', 160)->nullable();
            $table->string('route', 160)->nullable();
            $table->string('permissions', 80)->nullable();
            $table->boolean('is_header')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        DB::table('admin_links')->insert($this->defaultLinks());
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_links');
    }

    protected function defaultLinks(): array
    {
        $now = now();

        return [
            [
                'order_id' => 0,
                'name' => 'Administration',
                'icon' => null,
                'link' => null,
                'route' => null,
                'permissions' => null,
                'is_header' => true,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
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
                'created_at' => $now,
                'updated_at' => $now,
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
                'created_at' => $now,
                'updated_at' => $now,
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
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'order_id' => 4,
                'name' => 'Logs',
                'icon' => 'ix:log',
                // Absolute href, not a route name: the log viewer is a plain
                // page, and an Inertia visit would open it in the modal frame
                // with its own asset paths resolved against the wrong base.
                'link' => '/admin/log-viewer',
                'route' => null,
                'permissions' => 'sysper-log-view',
                'is_header' => false,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
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
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
    }
};
