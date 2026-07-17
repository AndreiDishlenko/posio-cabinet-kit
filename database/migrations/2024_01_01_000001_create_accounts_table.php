<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The tenant unit CabinetKit organizes everything around. Kept deliberately
     * thin (name + settings jsonb) — business-specific columns (currency,
     * subscription plan, etc.) belong in a host migration that extends this
     * table, not here.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained(config('cabinet-kit.users_table', 'users'))->cascadeOnDelete();
            $table->string('name');
            $table->json('settings')->nullable(); // MySQL forbids defaults on JSON; HasCustomFields treats null as '{}'
            $table->timestamp('expire')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
