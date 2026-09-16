<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registration approval by an administrator. Only a self-registered user gets
     * the "awaiting approval" mark, and only while the mode is on — existing,
     * invited and built-in users never carry it and keep signing in as before.
     * Added conditionally so a host that already carries these columns keeps
     * them untouched.
     */
    public function up(): void
    {
        $table = config('cabinet-kit.users_table', 'users');

        foreach ($this->columns() as $column => $define) {
            if (Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($define) {
                $define($blueprint);
            });
        }
    }

    public function down(): void
    {
        $table = config('cabinet-kit.users_table', 'users');

        foreach (array_keys($this->columns()) as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropColumn($column);
            });
        }
    }

    protected function columns(): array
    {
        return [
            'approval_requested_at' => fn (Blueprint $table) => $table->timestamp('approval_requested_at')->nullable(),
            'approved_at' => fn (Blueprint $table) => $table->timestamp('approved_at')->nullable(),
            'approved_by' => fn (Blueprint $table) => $table->unsignedBigInteger('approved_by')->nullable(),
        ];
    }
};
