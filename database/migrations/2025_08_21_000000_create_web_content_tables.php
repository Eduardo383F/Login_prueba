<?php


// database/migrations/2025_08_21_000000_create_web_content_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Página "About us"
        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();     // 'about'
            $t->string('title');
            $t->longText('content')->nullable();
            $t->json('meta')->nullable();     // redes, imágenes, etc.
            $t->timestamps();
        });

        // Ofertas / Paquetes
        Schema::create('offers', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('description')->nullable();
            $t->unsignedTinyInteger('discount_percent')->nullable(); // 0-100
            $t->decimal('price_from', 10, 2)->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->boolean('is_active')->default(true);
            $t->string('image_url')->nullable();
            $t->timestamps();
        });

        // Noticias
        Schema::create('news', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('excerpt')->nullable();
            $t->longText('body')->nullable();
            $t->boolean('is_published')->default(true);
            $t->timestamp('published_at')->nullable();
            $t->string('image_url')->nullable();
            $t->timestamps();
        });

        // Testimonios
        Schema::create('testimonials', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedTinyInteger('rating')->default(5); // 1..5
            $t->text('comment');
            $t->boolean('is_published')->default(true);
            $t->timestamps();
        });

        // Eventos
        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->string('location')->nullable();
            $t->text('description')->nullable();
            $t->boolean('is_published')->default(true);
            $t->string('image_url')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('events');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('news');
        Schema::dropIfExists('offers');
        Schema::dropIfExists('pages');
    }
};
