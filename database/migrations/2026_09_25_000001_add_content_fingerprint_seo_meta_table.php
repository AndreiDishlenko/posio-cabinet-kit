<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Дата изменения страницы для карты сайта. Отпечаток содержания запоминается, чтобы
// дата сдвигалась только при реальной правке, а не при каждой генерации карты.
return new class extends Migration
{
    public function up(): void
    {
        // Хост, у которого таблица общая с исходным проектом, мог завести колонки раньше.
        if ( !Schema::hasTable('seo_meta') || Schema::hasColumn('seo_meta', 'content_fingerprint') )
            return;

        Schema::table('seo_meta', function (Blueprint $table) {
            $table->string('content_fingerprint', 40)->nullable();
            $table->timestamp('content_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        if ( !Schema::hasColumn('seo_meta', 'content_fingerprint') )
            return;

        Schema::table('seo_meta', function (Blueprint $table) {
            $table->dropColumn(['content_fingerprint', 'content_updated_at']);
        });
    }
};
