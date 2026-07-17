<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guest membership — a user invited into an account they don't own.
     * The account owner never appears here (accounts.owner_id covers that).
     * The per-account role itself lives in Spatie's model_has_roles
     * (team_id = account_id), not on this pivot.
     */
    public function up(): void
    {
        Schema::create('user_has_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(config('cabinet-kit.users_table', 'users'))->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_has_accounts');
    }
};
