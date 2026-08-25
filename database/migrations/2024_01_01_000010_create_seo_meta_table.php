<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Постраничная SEO-мета: одна строка на пару «маршрут + локаль». Пустая локаль
// означает «для всех локалей» и уступает строке с конкретной локалью.
return new class extends Migration
{
    public function up(): void
    {
        if ( Schema::hasTable('seo_meta') )
            return;

        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();

            // Базовое имя маршрута, без суффикса локали (`home`, а не `home.uk`).
            $table->string('route_name');
            $table->string('locale')->nullable();
            $table->string('page_name')->nullable();

            // SEO-поля
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('index')->nullable()->default(0);

            // Open Graph
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();

            // Twitter
            $table->string('twitter_title')->nullable();
            $table->text('twitter_description')->nullable();
            $table->string('twitter_image')->nullable();

            // JSON-LD
            $table->boolean('jsonld_add_software')->default(false);
            $table->boolean('jsonld_add_organization')->default(false);

            // Карта сайта
            $table->string('changeFrequency')->default('monthly');
            $table->float('priority')->default(1.0);
            $table->boolean('is_published')->default(true);

            $table->softDeletes();
            $table->timestamps();
        });

        // Заготовка главной страницы заводится здесь же, а не только установщиком:
        // проект, который получил раздел с обновлением пакета, установку заново
        // не проходит, а пустой раздел выглядит сломанным.
        (new \Posio\CabinetKit\Database\Seeders\CabinetKitSeoSeeder())->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
    }
};
