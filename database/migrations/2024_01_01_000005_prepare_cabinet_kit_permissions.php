<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles') && ! Schema::hasColumn('roles', 'is_system')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->boolean('is_system')->default(0)->after('guard_name');
            });
        }

        if (Schema::hasTable('permissions') && ! Schema::hasColumn('permissions', 'is_system')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->boolean('is_system')->default(1)->after('guard_name');
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions') && Schema::hasColumn('permissions', 'is_system')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropColumn('is_system');
            });
        }

        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'is_system')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('is_system');
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
