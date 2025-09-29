<?php

// database/migrations/2025_09_10_042809_create_pages_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            Schema::create('pages', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 80)->unique();
                $table->string('title', 150);
                $table->longText('content');
                $table->json('meta')->nullable();
                $table->boolean('is_published')->default(true);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
            });
        }

        // Si la tabla ya existe pero le faltan columnas, las agregamos:
        if (Schema::hasTable('pages')) {
            Schema::table('pages', function (Blueprint $table) {
                if (! Schema::hasColumn('pages', 'is_published')) {
                    $table->boolean('is_published')->default(true)->after('meta');
                }
                if (! Schema::hasColumn('pages', 'published_at')) {
                    $table->timestamp('published_at')->nullable()->after('is_published');
                }
                if (! Schema::hasColumn('pages', 'meta')) {
                    $table->json('meta')->nullable()->after('content');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
