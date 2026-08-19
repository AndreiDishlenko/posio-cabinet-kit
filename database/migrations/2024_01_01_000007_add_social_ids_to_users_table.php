<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Social sign-in matches a returning person by the identifier their provider
     * issues, not by email: Apple only reveals an address on the first sign-in,
     * and either provider may hand over one that already belongs to another row.
     * Added conditionally so a host that already carries these columns keeps
     * them untouched.
     */
    public function up(): void
    {
        $table = config('cabinet-kit.users_table', 'users');

        foreach (['google_id', 'apple_id'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->string($column)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        $table = config('cabinet-kit.users_table', 'users');

        foreach (['google_id', 'apple_id'] as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropColumn($column);
            });
        }
    }
};
