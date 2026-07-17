<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AdminKit stores small per-user preferences (current_account, menu_groups,
     * onboarding flags, ...) in a `settings` json column via HasSettings. Added
     * conditionally — a host that already has an equivalent jsonb column can
     * rename it in config/admin-kit.php (`user_settings_column`) instead of
     * running this migration.
     */
    public function up(): void
    {
        $table = config('admin-kit.users_table', 'users');
        $column = config('admin-kit.user_settings_column', 'settings');

        if (! Schema::hasColumn($table, $column)) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->json($column)->default('{}');
            });
        }
    }

    public function down(): void
    {
        $table = config('admin-kit.users_table', 'users');
        $column = config('admin-kit.user_settings_column', 'settings');

        if (Schema::hasColumn($table, $column)) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropColumn($column);
            });
        }
    }
};
