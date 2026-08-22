<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

// Dated far ahead on purpose: the host publishes Spatie's table migration under
// the date of the install, so any ordinary timestamp here would sort before it
// and find no tables to shape.
return new class extends Migration
{
    public function up(): void
    {
        $this->adoptPivotsUnderTheirDefaultNames();

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

    // A project that already used Spatie before the kit arrived has its role
    // pivots built under Spatie's own names: the config patch alone renames
    // nothing, and every query then hits a table that does not exist.
    protected function adoptPivotsUnderTheirDefaultNames(): void
    {
        foreach (['model_has_roles', 'model_has_permissions'] as $default) {
            $configured = config("permission.table_names.{$default}", $default);

            if ($configured !== $default && Schema::hasTable($default) && ! Schema::hasTable($configured)) {
                Schema::rename($default, $configured);
            }

            $this->adoptMorphKey($configured);
        }
    }

    protected function adoptMorphKey(string $table): void
    {
        $configured = config('permission.column_names.model_morph_key', 'model_id');

        if ($configured === 'model_id' || ! Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasColumn($table, 'model_id') && ! Schema::hasColumn($table, $configured)) {
            Schema::table($table, function (Blueprint $blueprint) use ($configured) {
                $blueprint->renameColumn('model_id', $configured);
            });
        }
    }

    // Only the flag columns are given back. Pivot names and the morph key follow
    // the config, not this migration, so putting Spatie's defaults back on a
    // rollback would leave the tables named for a config nothing reads.
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
